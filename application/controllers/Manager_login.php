<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Manager_login extends MY_Controller {

    /**
     * 管理员 操作控制器
     * @version 1.0
     * @Copyright (C) 2017, Tianhuan Co., Ltd.
    */
	public function __construct()
    {
        parent::__construct();
        $this->load->model('manager_model');

    }

    /**
     * 登陆页面
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-29
     */
    public function index($flag = null)
	{
        $this->assign('flag',$flag);
        $this->display('manager/login/index.html');
	}

    /**
     * 账号登陆
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-29
     */
    public function check_login(){
        $rs = $this->manager_model->check_login();
        if($rs > 0){
            redirect(base_url('/manager/index'));
            exit();
        }else{
            redirect(base_url('/manager_login/index/'.$rs));
        }
    }

    /**
     * 验证码获取函数
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-29
     */
    public function get_cap(){
        $vals = array(
            //'word'      => 'Random word',
            'img_path'  => './upload/captcha/',
            'img_url'   => '/upload/captcha/',
            'img_width' => '120',
            'img_height'    => 30,
            'expiration'    => 7200,
            'word_length'   => 4,
            'font_size' => 18,
            'img_id'    => 'Imageid',
            'pool'      => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',

            // White background and border, black text and red grid
            'colors'    => array(
                'background' => array(255, 255, 255),
                'border' => array(255, 255, 255),
                'text' => array(0, 0, 0),
                'grid' => array(255, 40, 40)
            )
        );

        $rs = create_captcha($vals);
        $this->session->set_flashdata('cap', $rs['word']);
    }


    public function save_pics4con($time){
        $this->load->library('image_lib');

        if (is_readable('./././upload/consignment') == false) {
            mkdir('./././upload/consignment');
        }
        if (is_readable('./././upload/consignment/'.$time) == false) {
            mkdir('./././upload/consignment/'.$time);
        }
        $path = './././upload/consignment/'.$time;

        //设置缩小图片属性
        $config_small['image_library'] = 'gd2';
        $config_small['create_thumb'] = TRUE;
        $config_small['quality'] = 80;
        $config_small['maintain_ratio'] = TRUE; //保持图片比例
        $config_small['new_image'] = $path;
        $config_small['width'] = 300;
        $config_small['height'] = 190;

        //设置原图限制
        $config['upload_path'] = $path;
        $config['allowed_types'] = 'gif|jpg|png|jpeg';
        $config['max_size'] = '10000';
        $config['encrypt_name'] = true;
        $this->load->library('upload', $config);

        if($this->upload->do_upload()){
            $data = $this->upload->data();//返回上传文件的所有相关信息的数组
            $config_small['source_image'] = $data['full_path']; //文件路径带文件名
            $this->image_lib->initialize($config_small);
            $this->image_lib->resize();

            echo 1;
        }else{
            echo -1;
        }
        exit;
    }

    //ajax获取图片信息
    public function get_pics4con($time){
        $this->load->helper('directory');
        $path = './././upload/consignment/'.$time;
        $map = directory_map($path);
        $data = array();
        //整理图片名字，取缩略图片
        foreach($map as $v){
            if(substr(substr($v,0,strrpos($v,'.')),-5) == 'thumb'){
                $data['img'][] = $v;
            }
        }
        $data['time'] = $time;
        echo json_encode($data);
    }

    /**
     * 获取可用面签经理列表
     */
    public function get_mx_list4loan() {
        $this->load->model('common4manager_model', 'cm_model');
        $data = $this->cm_model->get_mx_list4loan();
        $this->assign('data', $data);
        $this->display('manager/loan/show_mx_list4loan.html');
    }

    /**
     * 获取可用风控经理列表
     */
    public function get_fk_list4loan() {
        $this->load->model('common4manager_model', 'cm_model');
        $data = $this->cm_model->get_fk_list4loan();
        $this->assign('data', $data);
        $this->display('manager/loan/show_fk_list4loan.html');
    }

    /**
     * 获取可用权证(银行)经理列表
     */
    public function get_qz_list4loan() {
        $this->load->model('common4manager_model', 'cm_model');
        $data = $this->cm_model->get_qz_list4loan();
        $this->assign('data', $data);
        $this->display('manager/loan/show_qz_list4loan.html');
    }

    /**
     * 获取可用权证(交易中心)经理列表
     */
    public function get_fc_list4loan() {
        $this->load->model('common4manager_model', 'cm_model');
        $data = $this->cm_model->get_fc_list4loan();
        $this->assign('data', $data);
        $this->display('manager/loan/show_fc_list4loan.html');
    }

    /**
     * 服务管家列表
     */
    public function get_admin_list4user() {
        $this->load->model('common4manager_model', 'cm_model');
        $data = $this->cm_model->get_fw_list();
        $this->assign('data', $data);
        $this->display('manager/users/show_admin_list4user.html');
    }

    public function get_brand_list4user(){
        $data = $this->manager_model->get_brand4select();
        $this->assign('brand_list', $data);
        $this->display('manager/users/show_brand_list4user.html');
    }

    public function get_storesByBrand4user(){
        $this->load->model('common4manager_model', 'cm_model');
        $brand_id = trim($this->input->post('sel_brand_id'));
        $res = $this->cm_model->get_storesByBrand4user($brand_id);
        $this->ajaxReturn($res);
    }

    public function get_status_list4loan() {

        $this->display('manager/loan/show_status_list4loan.html');
    }
}
