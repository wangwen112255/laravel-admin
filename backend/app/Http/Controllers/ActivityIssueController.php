<?php

namespace App\Http\Controllers;

use Common\Models\ActivityIssue;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ActivityIssueController extends Controller
{
    private $filed = [
        'start_at'      => '',//(string)Carbon::now()->startOfWeek()
        'end_at'        => '',//(string)Carbon::now()->endOfWeek()
        'extra'         => [],
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function postIndex(Request $request)
    {
        $id    = (int)$request->get('id', 1);
        $page  = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit');

        $start = ($page - 1) * $limit;

        $data = [
            'total'             => 0,
            'activity_issue'    => [],
        ];

        $activity_issue = ActivityIssue::select([
            'activity_issue.id',
            'activity.ident',
            'activity_issue.activity_id',
            'activity_issue.start_at',
            'activity_issue.end_at',
            'activity_issue.extra',
            'activity_issue.created_at',
            'activity_issue.updated_at'
        ])
            ->leftJoin('activity','activity.id','activity_issue.activity_id')
            ->where('activity_issue.activity_id',$id)
            ->orderBy('activity_issue.id', 'desc')
            ->skip($start)
            ->take($limit)
            ->get();

        $data['total'] = ActivityIssue::count();

        if (!$activity_issue->isEmpty()) {
            $data['activity_issue'] = $activity_issue->toArray();
        }

        foreach($data['activity_issue'] as $key => $issue){
            $data['activity_issue'][$key]['extra'] = json_decode($issue['extra'],true);
        }

        return $this->response(1, 'Success!', $data);
    }


    public function postCreate(Request $request)
    {
        //'activity_id'   => '',
    }

    public function getEdit(Request $request)
    {
        $id = (int)$request->get('id',0);

        $activity_issue = ActivityIssue::select([
            'activity_issue.id',
            'activity.title',
            'activity_issue.activity_id',
            'activity_issue.start_at',
            'activity_issue.end_at',
            'activity_issue.extra',
        ])
            ->leftJoin('activity','activity.id','activity_issue.activity_id')
            ->where('activity_issue.id',$id)
            ->first();

        if (empty($activity_issue)) {
            return $this->response(0, '活动不存在');
        }

        $activity_issue = $activity_issue->toArray();

        $activity_issue['extra'] = json_decode($activity_issue['extra'],true);

        foreach(\Storage::disk('public')->files('activity') as $file_name){
            $activity_issue['file_list'][] = [
                'name'  => explode('/',$file_name)[1],
                'url'   => \Storage::url($file_name)
            ];
        }

        $activity_issue['file_path'] = '/storage/activity/';

        return $this->response(1, 'success', $activity_issue);
    }

    public function putEdit(Request $request)
    {
        $id = (int)$request->get('id');

        $activity_issue = ActivityIssue::find($id);

        if (empty($activity_issue)) {
            return $this->response(0, '活动不存在');
        }

        foreach( $this->filed as $filed => $default_val ){
            if( $filed == 'start_at'){
                $activity_issue->$filed = $request->get('activity_at')[0] ?? (string)Carbon::now()->startOfWeek();
            }elseif( $filed == 'end_at'){
                $activity_issue->$filed = $request->get('activity_at')[1] ?? (string)Carbon::now()->endOfWeek();
            }elseif( $filed == 'extra'){
                $activity_issue->$filed = json_encode($request->get($filed,$default_val));
            }else{
                $activity_issue->$filed = $request->get($filed,$default_val);
            }
        }

        if ($activity_issue->save()) {
            return $this->response(1, '编辑成功');
        } else {
            return $this->response(0, '编辑失败');
        }
    }

    /**
     * 开奖
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function putOpen(Request $request)
    {
        $id = (int)$request->get('id');
        $code = (int)$request->get('code'); // code校验三位数字

        $activity_issue = ActivityIssue::find($id);

        if (empty($activity_issue)) {
            return $this->response(0, '活动不存在');
        }

        $extra = json_decode($activity_issue->extra,true);
        if( !empty($extra['code']) ){
            return $this->response(0, '对不起，已开奖不可重新输入号码！');
        }

        $extra['code'] = $code;

        $activity_issue->extra = $extra;

        if ($activity_issue->save()) {
            return $this->response(1, '开奖成功');
        } else {
            return $this->response(0, '开奖失败');
        }
    }

    /**
     * 删除方法
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteDelete(Request $request)
    {
        return $this->response(1, '系统不支持删除功能!');

        $id = $request->get('id');
        if( ActivityIssue::where('id','=',$id)->delete() ){
            return $this->response(1,'删除成功！');
        }else{
            return $this->response(0,'删除失败！');
        }
    }

    /**
     * 开奖
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function putOpenCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'    => 'required|numeric',
            'code'  => 'required|numeric|min:0|max:999',
        ],[
            'id.required'   => 'ID不能为空',
            'id.numeric'    => 'ID格式错误',
            'code.required' => '号码不能为空',
            'code.numeric'  => '号码格式错误',
            'code.min'      => '号码不能小于0',
            'code.max'      => '号码不能大于999',
        ]);

        if ($validator->fails()) {
            return $this->response(0, $validator->getMessageBag()->all()[0]);
        }

        $id = $request->get('id');
        $code = $request->get('code');

        $effect = DB::update("UPDATE activity_issue SET extra= jsonb_set(extra::jsonb,'{code}',:code::text::jsonb) where id=:id",[
            'code'  => str_pad($code,3,STR_PAD_LEFT),
            'id'    => $id
        ]);

        if( $effect ){
            return $this->response(1, '开奖成功');
        }

        return $this->response(0, '开奖失败！');
    }
}