<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ajax_api extends CI_Controller {

    /**
     * Ajax控制器
     * @version 1.0
     * @Copyright (C) 2017, Tianhuan Co., Ltd.
     */
	public function __construct()
    {
        parent::__construct();
        ini_set('date.timezone','Asia/Shanghai');
        $this->load->library('image_lib');
        $this->load->helper('directory');
        $this->load->model('manager_model');
    }

    /**
     * 注册所使用的短信验证
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_register_code($phone){
        $check = $this->manager_model->get_userByPhone(trim($phone));
        if($check){
            echo -2;
        }else{
            $this->get_phone_code(trim($phone));
        }
        die;
    }


    /**
     * 修改信息所使用的短信验证
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_edit_code($phone){
        $check = $this->manager_model->get_userByPhone(trim($phone));
        if($check){
            if($check['id'] != $this->session->userdata('driver_id')){
                echo -2;exit();
            }
        }
        $this->get_phone_code(trim($phone));
        die;
    }

    /**
     * 登陆所使用的短信验证
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_login_code($phone){
        $check = $this->manager_model->get_userByPhone(trim($phone));
        if($check){
            if($check['status'] != 1){
                echo -4;//此会员已经禁用
                exit();
            }
            if($check['type'] != 1){
                echo -3;//此会员不是认证司机
                exit();
            }
            $this->get_phone_code(trim($phone));
        }else{
            echo -2;
        }
        die;
    }

    /**
     * 商户登陆所使用的短信验证
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_login_code4business($phone){
        $check = $this->manager_model->get_businessByPhone(trim($phone));
        if($check == 1){
            $this->get_phone_code(trim($phone));
        }else{
            echo $check;
        }
        die;
    }

    /**
     * 生成大屏显示的数据
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-05-10
     */
    public function show_data(){
        header("Access-Control-Allow-Origin: *");
        $rs = $this->ajax_api_model->show_data();
        echo json_encode($rs);
    }

    public function wx_notify(){
        $this->load->config('wxpay_config');
        $wx_config = array();
        $wx_config['appid']=$this->config->item('appid');
        $wx_config['mch_id']=$this->config->item('mch_id');
        $wx_config['apikey']=$this->config->item('apikey');
        $wx_config['appsecret']=$this->config->item('appsecret');
        $wx_config['sslcertPath']=$this->config->item('sslcertPath');
        $wx_config['sslkeyPath']=$this->config->item('sslkeyPath');
        $this->load->library('wxpay/Wechatpay',$wx_config);
        $data_array = $this->wechatpay->get_back_data();
        if($data_array['result_code']=='SUCCESS' && $data_array['return_code']=='SUCCESS'){
            if($this->ajax_api_model->wx_change_order($data_array['out_trade_no']) != 1){
                return 'FAIL';
            }else{
                return 'SUCCESS';
            }
        }
    }


}
