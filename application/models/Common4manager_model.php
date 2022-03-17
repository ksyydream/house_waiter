<?php
/**
 * Created by PhpStorm.
 * User: yangyang
 * Date: 16/6/3
 * Time: 下午3:22
 */
class Common4manager_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }


    //获取工作权限列表
    public function get_work_role($status = null){
        $this->db->select()->from('work_role');
        if($status){
            $this->db->where('status', $status);
        }
        $res = $this->db->get()->result_array();
        return $res;
    }

    public function get_admin_work_list($role_id){
        $this->db->select('a.admin_id, a.user, a.role_id, w.name role_name, a.admin_name')->from('admin a');
        $this->db->join('work_role w','a.role_id = w.id','left');
        $this->db->where('a.status', 1);
        if($role_id > 0){
            $this->db->where('a.role_id', $role_id);
        }else{
            $this->db->where('a.role_id >', 0);
            $this->db->where('a.role_id <>', 2);
        }
        $data = $this->db->get()->result_array();
        return $data;
    }

    //【暂时不用】 先保留
    public function get_admin_work_list_old($role_id){
        if(is_array($role_id)){
            $data = $this->db->select('a.*')->from('admin a')
                ->join('admin_work_role awr','a.admin_id = awr.admin_id','inner')
                ->where('a.status', 1)
                ->where_in('awr.r_id', $role_id)
                ->group_by('a.admin')
                ->get()->result_array();
        }else{
            $data = $this->db->select('a.*')->from('admin a')
                ->join('admin_work_role awr','a.admin_id = awr.admin_id','inner')
                ->where(array('a.status' => 1, 'awr.r_id' => $role_id))->get()->result_array();
        }
        return $data;
    }

    //获取可以被分配的管理员名单
    public function get_admin_fp_list() {
        return $this->get_admin_work_list(-1);
    }

    //按照code 来获取单独角色的人员列表，也可以获取所有
    public function get_single_list(){
        $action_ = $this->input->post('identity_code') ? $this->input->post('identity_code') : '';
        $this->db->select('a.admin_id, a.user, a.role_id, w.name role_name, a.admin_name')->from('admin a');
        $this->db->join('work_role w','a.role_id = w.id','inner');
        $this->db->where('a.status', 1);
        if($action_)
            $this->db->where('w.code', $action_);
        $this->db->where('a.role_id <>', 2);
        $data = $this->db->get()->result_array();
        return $data;
    }
    //服务管家
    public function get_fw_list() {
        return $this->get_admin_work_list(1);
    }



    public function get_mx_list4loan(){

    }

    public function get_fk_list4loan() {
        $data = $this->db->select()->from('admin')->where(array('status' => 1, 'role_id' => 2))->get()->result_array();
        return $data;
    }

    public function get_qz_list4loan() {
        $data = $this->db->select()->from('admin')->where(array('status' => 1, 'role_id' => 3))->get()->result_array();
        return $data;
    }

    public function get_fc_list4loan() {
        $data = $this->db->select()->from('admin')->where(array('status' => 1, 'role_id' => 7))->get()->result_array();
        return $data;
    }

    public function get_admin_list4user() {
        $data = $this->db->select()->from('admin')->where(array('status' => 1, 'role_id <>' => -1))->get()->result_array();
        return $data;
    }

    public function get_storesByBrand4user($brand_id =0,$status= 0){
        $this->db->select();
        $this->db->from("brand_stores");
        $this->db->where('is_delete', -1);
        $this->db->where('brand_id', $brand_id);
        if($status)
            $this->db->where('status', $status);
        $data = $this->db->get()->result_array();
        return $data;
    }
    /** check fun */



}