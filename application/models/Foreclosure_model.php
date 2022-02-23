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

    //赎楼想去
    private function foreclosure_list($where, $order_1 = 'a.create_time', $order_2 = 'desc', $page_ = 1, $limit_ = -1){
        $data['limit'] = $limit_ < 0 ?$this->mini_limit : $limit_;//每页显示多少调数据
        $data['keyword'] = $this->input->post('keyword')?trim($this->input->post('keyword')):null;
        $data['brand_id'] = $this->input->post('brand_id')?trim($this->input->post('brand_id')):null;
        $data['store_id'] = $this->input->post('store_id')?trim($this->input->post('store_id')):null;
        $data['user_id'] = $this->input->post('user_id')?trim($this->input->post('user_id')):null;
        $data['flag'] = $this->input->post('flag') ? trim($this->input->post('flag')) : null; //默认查进行中 取消默认
        $data['status'] = $this->input->post('status') ? trim($this->input->post('status')) : null;
        $page = $this->input->post('page')?trim($this->input->post('page')) : $page_;

        $this->db->select('count(DISTINCT a.foreclosure_id) num');
        $this->db->from('foreclosure a');
        $this->db->join('foreclosure_buyers wb', 'a.foreclosure_id = wb.foreclosure_id', 'left');
        $this->db->join('foreclosure_borrowers ws', 'a.foreclosure_id = ws.foreclosure_id', 'left');
        $this->db->join('users u','a.user_id = u.user_id','left');
        if ($where && $where != array())
            $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('wb.buyer_name', $data['keyword']);
            $this->db->or_like('wb.buyer_card', $data['keyword']);
            $this->db->or_like('ws.borrower_card', $data['keyword']);
            $this->db->or_like('ws.borrower_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['status']){
            $this->db->where('a.status', $data['status']);
        }
        if($data['user_id']){
            $this->db->where('a.user_id', $data['user_id']);
        }
        if($data['brand_id']){
            $this->db->where('a.brand_id', $data['brand_id']);
        }
        if($data['store_id']){
            $this->db->where('a.store_id', $data['store_id']);
        }
        $num = $this->db->get()->row();
        $res['total_rows'] = $num->num;
        $res['total_page'] = ceil($res['total_rows'] / $data['limit']);
        $this->db->select("a.foreclosure_id,a.work_no,a.total_price,a.borrow_money,a.flag,
        u.rel_name handle_name,u.mobile handle_mobile,
        u1.rel_name create_name,u1.mobile create_mobile,
        FROM_UNIXTIME(a.create_time) create_date_,
       a.status,
        fw.admin_name fw_name,fw.phone fw_phone,
        sl.admin_name sl_name,sl.phone sl_phone,
        cw.admin_name cw_name,cw.phone cw_phone,
         bd.brand_name,s.store_id,s.store_name");
        $this->db->from('foreclosure a');
        $this->db->join('foreclosure_buyers wb', 'a.foreclosure_id = wb.foreclosure_id', 'left');
        $this->db->join('foreclosure_borrowers ws', 'a.foreclosure_id = ws.foreclosure_id', 'left');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('brand_stores s','a.store_id = s.store_id','left');
        $this->db->join('admin fw', 'a.fw_admin_id = fw.admin_id', 'left');
        $this->db->join('admin sl', 'a.sl_admin_id = sl.admin_id', 'left');
        $this->db->join('admin cw', 'a.cw_admin_id = cw.admin_id', 'left');
        if ($where && $where != array())
            $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('wb.buyer_name', $data['keyword']);
            $this->db->or_like('wb.buyer_card', $data['keyword']);
            $this->db->or_like('ws.borrower_card', $data['keyword']);
            $this->db->or_like('ws.borrower_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['status']){
            $this->db->where('a.status', $data['status']);
        }
        if($data['brand_id']){
            $this->db->where('a.brand_id', $data['brand_id']);
        }
        if($data['user_id']){
            $this->db->where('a.user_id', $data['user_id']);
        }
        if($data['store_id']){
            $this->db->where('a.store_id', $data['store_id']);
        }
        $this->db->order_by($order_1, $order_2);
        $this->db->order_by('a.foreclosure_id', 'desc'); //给个默认排序
        $this->db->group_by('a.foreclosure_id');
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $res['res_list'] = $this->db->get()->result_array();
        //return $this->db->last_query();
        foreach($res['res_list'] as $k => $v){
            $b_list_ = $this->db->select('group_concat(distinct wb.buyer_name ORDER BY wb.id) b_name_list')->from('foreclosure_buyers wb')
                ->where(array('wb.foreclosure_id' => $v['foreclosure_id']))->get()->row_array();
            $b_name_list_ = $b_list_ ? $b_list_['b_name_list'] : '';
            $res['res_list'][$k]['buyers_list'] = $b_name_list_;

            $s_list_ = $this->db->select('group_concat(distinct ws.borrower_name ORDER BY ws.id) s_name_list')->from('foreclosure_borrowers ws')
                ->where(array('ws.foreclosure_id' => $v['foreclosure_id']))->get()->row_array();
            $s_name_list_ = $s_list_ ? $s_list_['s_name_list'] : '';
            $res['res_list'][$k]['borrowers_list'] = $s_name_list_;
        }
        $res['data'] = $data;
        return $res;
    }

    public function foreclosure_info($foreclosure_id){
        $select = "a.*,
        u.rel_name handle_name,u.mobile handle_mobile,
        u1.rel_name create_name,u1.mobile create_mobile,
        FROM_UNIXTIME(a.create_time) create_date_,
         FROM_UNIXTIME(a.submit_time) submit_cdate_,
        fw.admin_name fw_name,fw.phone fw_phone,
        sl.admin_name sl_name,sl.phone sl_phone,
        cw.admin_name cw_name,cw.phone cw_phone,
         bd.brand_name,s.store_id,s.store_name";
        $this->db->select($select);
        $this->db->from('foreclosure a');
        $this->db->join('foreclosure_buyers wb', 'a.foreclosure_id = wb.foreclosure_id', 'left');
        $this->db->join('foreclosure_borrowers ws', 'a.foreclosure_id = ws.foreclosure_id', 'left');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('brand_stores s','a.store_id = s.store_id','left');
        $this->db->join('admin fw', 'a.fw_admin_id = fw.admin_id', 'left');
        $this->db->join('admin sl', 'a.sl_admin_id = sl.admin_id', 'left');
        $this->db->join('admin cw', 'a.cw_admin_id = cw.admin_id', 'left');
        $foreclosure_info = $this->db->where('a.foreclosure_id', $foreclosure_id)->get()->row_array();
        if(!$foreclosure_info)
            return $this->fun_fail('未找到相关申请!');
        $this->db->select('*');
        $this->db->from('foreclosure_buyers');
        $this->db->where('foreclosure_id', $foreclosure_id);
        $foreclosure_info['buyers_list'] = $this->db->get()->result_array();
        $this->db->select('*');
        $this->db->from('foreclosure_borrowers');
        $this->db->where('foreclosure_id', $foreclosure_id);
        $foreclosure_info['borrowers_list'] = $this->db->get()->result_array();
        return $this->fun_success('获取成功!', $foreclosure_info);
    }

    /**
     *********************************************************************************************
     * 以下代码为管理员端 专用
     *********************************************************************************************
     */

    public function foreclosure_list4manager($page = 1, $where = array()){
        $data = $this->foreclosure_list($where, 'a.create_time', 'desc', $page, $this->limit);
        return $data;
    }
}