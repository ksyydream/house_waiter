<?php
/**
 * Created by PhpStorm.
 * User: bin.shen
 * Date: 5/2/16
 * Time: 09:56
 */

 if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once "Mini_controller.php";
class Mini_admin extends Mini_controller {
    private $admin_id;
    private $role_id;
    private $admin_info = array();
    public function __construct()
    {
        parent::__construct();
        $this->load->model('mini_admin_model');
        $this->load->model('loan_model');
        $this->load->model('warrants_model');
        $token = $this->get_header_token();
        if(!$token){
            $this->ajaxReturn(array('status' => -100, 'msg' => 'token缺失!', "result" => ''));
        }
        $admin_id_ = $this->get_token_uid($token,"ADMIN"); //可以不验证
        $check_re = $this->mini_admin_model->check_token($token, $admin_id_);
        if($check_re['status'] < 0){
            $this->ajaxReturn($check_re);
        }
        $this->admin_id = $check_re['result']['admin_id'];
        $this->role_id = $check_re['result']['role_id'];
        $this->mini_admin_model->update_admin_tt($this->admin_id); //操作就更新登录时间
        $this->mini_admin_model->save_admin_log($this->admin_id); //保存操作记录
        //这里做 服务管家 和 财务人员的 权限控制
        if ($this->role_id != 1 && in_array($this->uri->segment(2),array('save_warrants','submit_warrants'))){
            $err_ = $this->mini_admin_model->fun_fail('你没有操作权限');
            $this->ajaxReturn($err_);
        }
        if ($this->role_id != 2 && in_array($this->uri->segment(2),array('',''))){
            $err_ = $this->mini_admin_model->fun_fail('你没有操作权限');
            $this->ajaxReturn($err_);
        }
    }

    public function get_admin_info(){
        $admin_info = $this->mini_admin_model->get_admin_info($this->admin_id);
        $this->ajaxReturn($admin_info);
    }

    //赎楼列表
    public function loan_list(){
        switch($this->role_id){
            case 1:
                $rs = $this->loan_model->loan_list4mx($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            case 2:
                $rs = $this->loan_model->loan_list4fk($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            case 3:
                //权证
                $rs = $this->loan_model->loan_list4qz($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            case 4:
                //财务
                $rs = $this->loan_model->loan_list4cw($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            case 5:
                //终审
                $rs = $this->loan_model->loan_list4zs($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            case 7:
                //权证 交易中心
                $rs = $this->loan_model->loan_list4fc($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            default:
                $this->ajaxReturn($this->loan_model->fun_fail("未找到可用数据！"));

        }

    }

    public function loan_info(){
        $loan_id = $this->input->post('loan_id');
        $rs = $this->loan_model->loan_info($loan_id);
        if ($rs['status'] != 1) {
            $this->ajaxReturn($rs);
        }
        //验证权限
        $loan_info = $rs['result'];
        //验证面签经理权限
        if($this->role_id == 1 && $loan_info['mx_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        //验证风控经理权限
        if($this->role_id == 2 && $loan_info['fk_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        //验证权证(银行)权限
        if($this->role_id == 3 && $loan_info['qz_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        //验证权证(交易中心)权限
        if($this->role_id == 7 && $loan_info['fc_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }


        //返回信息
        $this->ajaxReturn($this->loan_model->fun_success("获取成功！", $loan_info));
    }

    /**
     *********************************************************************************************
     * 以下代码为服务管家专用，即提单 和 人员指派
     *********************************************************************************************
     */

    public function save_warrants(){
        $res = $this->warrants_model->submit_warrants($this->admin_id, -1);
        $this->ajaxReturn($res);
    }

    public function submit_warrants(){
        $res = $this->warrants_model->submit_warrants($this->admin_id, 1);
        $this->ajaxReturn($res);
    }

    /**
     *********************************************************************************************
     * 以下代码为财务专员 专用，即回款和放款
     *********************************************************************************************
     */




}