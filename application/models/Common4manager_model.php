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
        $data = $this->db->select()->from('admin')->where(array('status' => 1, 'role_id' => $role_id))->get()->result_array();
        return $data;
    }

    //服务管家
    public function get_fw_list() {
        return $this->get_admin_work_list(1);
    }

    //网签经理
    public function get_wq_list() {
        return $this->get_admin_work_list(2);
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