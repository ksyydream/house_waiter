<?php
/**
 * Created by PhpStorm.
 * User: bin.shen
 * Date: 6/2/16
 * Time: 21:22
 */

class Foreclosure_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function save_foreclosure($user_id){
        $buyers = $this->input->post("buyers");;
        if(!is_array($buyers)){
            $buyers = json_decode($buyers,true);
        }
        foreach($buyers as $k_ => $v_){
            if(!isset($v_['buyer_name']) || trim($v_['buyer_name']) == "")
                return $this->fun_fail('存在买方姓名为空!');
            if(!isset($v_['buyer_phone']) || trim($v_['buyer_phone']) == "")
                return $this->fun_fail('存在买方电话为空!');
            if(!isset($v_['buyer_card']) || trim($v_['buyer_card']) == "")
                return $this->fun_fail('存在买方身份证为空!');
        }

        $borrowers = $this->input->post("borrowers");;
        if(!is_array($borrowers)){
            $borrowers = json_decode($borrowers,true);
        }
        if(!$borrowers)
            return $this->fun_fail('借款方不能为空!');
        foreach($borrowers as $k_ => $v_){
            if(!isset($v_['borrower_name']) || trim($v_['borrower_name']) == "")
                return $this->fun_fail('存在借款方姓名为空!');
            if(!isset($v_['borrower_phone']) || trim($v_['borrower_phone']) == "")
                return $this->fun_fail('存在借款方电话为空!');
            if(!isset($v_['borrower_card']) || trim($v_['borrower_card']) == "")
                return $this->fun_fail('存在借款方身份证为空!');
        }
        //DBY_problem 可能会有临时单据，没有用户ID
        $user_info = $this->readByID("users", 'user_id', $user_id);
        if(!$user_info)
            return $this->fun_fail('异常!');
        $brand_id = $user_info['brand_id'] ? $user_info['brand_id'] : -1;
        $store_id = $user_info['store_id'] ? $user_info['store_id'] : -1;
        $user_phone = $user_info['user_phone'] ? $user_info['user_phone'] : '';
        $data = array(
            'user_id' => $user_id,
            'create_user_id' => $user_id,
            'user_phone' => $user_phone,
            'create_user_phone' => $user_phone,
            'brand_id' => $brand_id,
            'store_id' => $store_id,
            'temp_rel_name' => $user_info['rel_name'],
            'modify_time' => time(),
            'create_time' => time(),
            'borrow_money' => trim($this->input->post('borrow_money')), //借款金额
            'borrow_money_user' => trim($this->input->post('borrow_money_user')), //借款金额 实际可能后面做修改
            'expect_use_time' => trim($this->input->post('expect_use_time')) ? trim($this->input->post('expect_use_time')) : null,                      //预计用款时间
            'bank_loan_type' => trim($this->input->post('bank_loan_type')), //贷款方式
            'hav_mortgage' => trim($this->input->post('hav_mortgage')), //按揭是否通过
            'order_type' => trim($this->input->post('order_type')) ? trim($this->input->post('order_type')) : 1,  //订单类型 1代表内部，2代表委外
            'total_price' => trim($this->input->post('total_price')), //成交总价
            'old_loan_balance' => trim($this->input->post('old_loan_balance')), //老贷余额
            'old_loan_setup' => trim($this->input->post('old_loan_setup')), //老贷机构
            'deposit' => trim($this->input->post('deposit')), //首付 定金
            'expect_mortgage_bank' => trim($this->input->post('expect_mortgage_bank')) ? trim($this->input->post('expect_mortgage_bank')) : null,       //预计按揭银行
            'expect_mortgage_money' => trim($this->input->post('expect_mortgage_money')) ? trim($this->input->post('expect_mortgage_money')) : null,    //预计按揭金额
            'mortgage_bank' => trim($this->input->post('expect_mortgage_bank')) ? trim($this->input->post('expect_mortgage_bank')) : null,       //实际按揭银行
            'mortgage_money' => trim($this->input->post('expect_mortgage_money')) ? trim($this->input->post('expect_mortgage_money')) : null,    //实际按揭金额
            'flag' => 1,
            'status' => 1,
        );

        //先验证关键数据是否有效
        if(!$data['borrow_money'] || $data['borrow_money'] <= 0){
            return $this->fun_fail('借款金额不能为空!');
        }
        $data['work_no'] = $this->get_workno();
        $fw_admin_id = $this->get_role_admin_id(1);
        $data['fw_admin_id'] = $fw_admin_id;
        $this->db->insert('foreclosure', $data);
        $foreclosure_id = $this->db->insert_id();
        //批量新增买方
        $buyers_insert_ = array();
        foreach($buyers as $k => $v){
            $b_insert_ = array(
                'buyer_name' => $v['buyer_name'],
                'buyer_phone' => $v['buyer_phone'],
                'buyer_card' => $v['buyer_card'],
                'foreclosure_id' => $foreclosure_id
            );
            $buyers_insert_[] = $b_insert_;
        }
        $this->db->insert_batch('foreclosure_buyers', $buyers_insert_);
        //批量新增卖方
        $borrowers_insert_ = array();
        foreach($borrowers as $k => $v){
            $s_insert_ = array(
                'borrower_name' => $v['borrower_name'],
                'borrower_phone' => $v['borrower_phone'],
                'borrower_card' => $v['borrower_card'],
                'foreclosure_id' => $foreclosure_id
            );
            $borrowers_insert_[] = $s_insert_;
        }
        $this->db->insert_batch('foreclosure_borrowers', $borrowers_insert_);
        return $this->fun_success('操作成功',array('foreclosure_id' => $foreclosure_id));
    }

    public function submit_foreclosure($foreclosure_id, $fw_admin_id){
        $foreclosure_info_ = $this->db->select()->from('foreclosure')->where(array('foreclosure_id' => $foreclosure_id))->get()->row_array();
        if (!$foreclosure_info_)
            return $this->fun_fail('赎楼单不存在!');
        if ($foreclosure_info_['flag'] != 1 || $foreclosure_info_['status'] != 1 )
            return $this->fun_fail('赎楼单状态已变更 不可提交!');
        if ($foreclosure_info_['fw_admin_id'] != $fw_admin_id)
            return $this->fun_fail('您没有此权证操作权限!');
        $buyers = $this->input->post("buyers");
        $buyers_insert_ = array();
        if(!is_array($buyers)){
            $buyers = json_decode($buyers,true);
        }
        foreach($buyers as $k_ => $v_){
            if(!isset($v_['buyer_name']) || trim($v_['buyer_name']) == "")
                return $this->fun_fail('存在买方姓名为空!');
            if(!isset($v_['buyer_phone']) || trim($v_['buyer_phone']) == "")
                return $this->fun_fail('存在买方电话为空!');
            if(!isset($v_['buyer_card']) || trim($v_['buyer_card']) == "")
                return $this->fun_fail('存在买方身份证为空!');
            $b_insert_ = array('buyer_name' => $v_['buyer_name'], 'buyer_phone' => $v_['buyer_phone'], 'buyer_card' => $v_['buyer_card'], 'foreclosure_id' => $foreclosure_id);
            $buyers_insert_[] = $b_insert_;
        }

        $borrowers = $this->input->post("borrowers");
        $borrowers_insert_ = array();
        if(!is_array($borrowers)){
            $borrowers = json_decode($borrowers,true);
        }
        if(!$borrowers)
            return $this->fun_fail('借款方不能为空!');
        foreach($borrowers as $k_ => $v_){
            if(!isset($v_['borrower_name']) || trim($v_['borrower_name']) == "")
                return $this->fun_fail('存在借款方姓名为空!');
            if(!isset($v_['borrower_phone']) || trim($v_['borrower_phone']) == "")
                return $this->fun_fail('存在借款方电话为空!');
            if(!isset($v_['borrower_card']) || trim($v_['borrower_card']) == "")
                return $this->fun_fail('存在借款方身份证为空!');
            $s_insert_ = array('borrower_name' => $v_['borrower_name'], 'borrower_phone' => $v_['borrower_phone'], 'borrower_card' => $v_['borrower_card'], 'foreclosure_id' => $foreclosure_id);
            $borrowers_insert_[] = $s_insert_;
        }

        $data = array(
            'modify_time' => time(),
            'submit_time' => time(),
            'borrow_money' => trim($this->input->post('borrow_money')), //借款金额
            'borrow_money_user' => trim($this->input->post('borrow_money_user')), //借款金额 实际可能后面做修改
            'expect_use_time' => trim($this->input->post('expect_use_time')) ? trim($this->input->post('expect_use_time')) : null,                      //预计用款时间
            'bank_loan_type' => trim($this->input->post('bank_loan_type')), //贷款方式
            'hav_mortgage' => trim($this->input->post('hav_mortgage')), //按揭是否通过
            'order_type' => trim($this->input->post('order_type')) ? trim($this->input->post('order_type')) : 1,  //订单类型 1代表内部，2代表委外
            'total_price' => trim($this->input->post('total_price')), //成交总价
            'old_loan_balance' => trim($this->input->post('old_loan_balance')), //老贷余额
            'old_loan_setup' => trim($this->input->post('old_loan_setup')), //老贷机构
            'deposit' => trim($this->input->post('deposit')), //首付 定金
            'expect_mortgage_bank' => trim($this->input->post('expect_mortgage_bank')) ? trim($this->input->post('expect_mortgage_bank')) : null,       //预计按揭银行
            'expect_mortgage_money' => trim($this->input->post('expect_mortgage_money')) ? trim($this->input->post('expect_mortgage_money')) : null,    //预计按揭金额
            'mortgage_bank' => trim($this->input->post('expect_mortgage_bank')) ? trim($this->input->post('expect_mortgage_bank')) : null,       //实际按揭银行
            'mortgage_money' => trim($this->input->post('expect_mortgage_money')) ? trim($this->input->post('expect_mortgage_money')) : null,    //实际按揭金额
            'status' => 2,
        );

        //先验证关键数据是否有效
        if(!$data['borrow_money'] || $data['borrow_money'] <= 0){
            return $this->fun_fail('借款金额不能为空!');
        }
        $sl_admin_id = $this->get_role_admin_id(5);
        $data['sl_admin_id'] = $sl_admin_id;
        $this->db->where(array('foreclosure_id' => $foreclosure_id, 'fw_admin_id' => $fw_admin_id))->update('foreclosure', $data);
        $this->db->where('foreclosure_id', $foreclosure_id)->delete('foreclosure_buyers');
        $this->db->where('foreclosure_id', $foreclosure_id)->delete('foreclosure_sellers');
        $this->db->insert_batch('foreclosure_buyers', $buyers_insert_);
        $this->db->insert_batch('foreclosure_borrowers', $borrowers_insert_);
        return $this->fun_success('操作成功',array('foreclosure_id' => $foreclosure_id));

    }
}