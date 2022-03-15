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
        $this->load->model('common4manager_model', 'c4m_model');
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
            //$err_ = $this->mini_admin_model->fun_fail('你没有操作权限');
            //$this->ajaxReturn($err_);
        }
        if ($this->role_id != 2 && in_array($this->uri->segment(2),array('',''))){
            //$err_ = $this->mini_admin_model->fun_fail('你没有操作权限');
            //$this->ajaxReturn($err_);
        }
    }

    public function get_admin_info(){
        $admin_info = $this->mini_admin_model->get_admin_info($this->admin_id);
        $this->ajaxReturn($admin_info);
    }

    //获取所有可分配管理员
    public function get_admin_fp_list(){
        $data = $this->c4m_model->get_admin_fp_list();
        $res_ = $this->mini_admin_model->fun_success('操作成功', $data);
        $this->ajaxReturn($res_);
    }

    //根据brand_id 获取用户列表
    public function get_users_by_brand_id(){
        $this->load->model('mini_user_model');
        $res_ = $this->mini_user_model->get_users_by_brand_id();
        $this->ajaxReturn($res_);
    }

    //获取需要处理 权证单各个状态的数量
    public function warrants_count4admin(){
        $res_ = $this->warrants_model->warrants_count4admin($this->admin_id, $this->role_id);
        $this->ajaxReturn($res_);
    }

    //服务管家各节点 需要设置人员的数量
    public function warrants_count4fwadmin(){
        $res_ = $this->warrants_model->warrants_count4FWadmin($this->admin_id, $this->role_id);
        $this->ajaxReturn($res_);
    }

    //获取 权证单详情
    public function warrants_info(){
        $warrants_id_ = $this->input->post('warrants_id');
        $check_ = $this->warrants_model->check_permission($warrants_id_, $this->admin_id, 1);
        if($check_['status'] != 1)
            $this->ajaxReturn($check_);
        $res_ = $this->warrants_model->warrants_info($warrants_id_);
        $this->ajaxReturn($res_);
    }

    //权证单 业务流程
    public function warrants_admin_log_list(){
        $res_ = $this->warrants_model->get_warrants_admin_log_list();
        $this->ajaxReturn($res_);
    }

   //获取等待网签的 权证单列表
    public function warrants_qw_1_list(){
        $res_ = $this->warrants_model->get_warrants_qw_1_list($this->admin_id);
        $this->ajaxReturn($res_);
    }

    /**
     *********************************************************************************************
     * 以下代码为服务管家专用，即提单 和 人员指派
     *********************************************************************************************
     */

    public function get_wq_admin_list(){
        $data = $this->c4m_model->get_wq_list();
        $res_ = $this->mini_admin_model->fun_success('操作成功', $data);
        $this->ajaxReturn($res_);
    }

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