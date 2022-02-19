<?php
/**
 * Created by PhpStorm.
 * User: bin.shen
 * Date: 6/2/16
 * Time: 21:22
 */

class Warrants_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }


    //新建赎楼业务
    public function save_warrants($user_id){

        $buyers = $this->input->post("buyers");;
        if(!is_array($buyers)){
            $buyers = json_decode($buyers,true);
        }
        if(!$buyers)
            return $this->fun_fail('买方不能为空!');

        foreach($buyers as $k_ => $v_){
            if(!isset($v_['buyer_name']) || trim($v_['buyer_name']) == "")
                return $this->fun_fail('存在买方姓名为空!');
            if(!isset($v_['buyer_phone']) || trim($v_['buyer_phone']) == "")
                return $this->fun_fail('存在买方电话为空!');
            if(!isset($v_['buyer_card']) || trim($v_['buyer_card']) == "")
                return $this->fun_fail('存在买方身份证为空!');
        }

        $sellers = $this->input->post("sellers");;
        if(!is_array($sellers)){
            $sellers = json_decode($sellers,true);
        }
        if(!$sellers)
            return $this->fun_fail('卖方不能为空!');
        foreach($sellers as $k_ => $v_){
            if(!isset($v_['seller_name']) || trim($v_['seller_name']) == "")
                return $this->fun_fail('存在卖方姓名为空!');
            if(!isset($v_['seller_phone']) || trim($v_['seller_phone']) == "")
                return $this->fun_fail('存在卖方电话为空!');
            if(!isset($v_['seller_card']) || trim($v_['seller_card']) == "")
                return $this->fun_fail('存在卖方身份证为空!');
        }

        //获取门店 大客户品牌
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
            'total_price' => trim($this->input->post('total_price')),                                                                               //成交总价
            'deposit' => trim($this->input->post('deposit')) ? trim($this->input->post('deposit')) : null,                                          //首付、定金
            'expect_mortgage_bank' => trim($this->input->post('expect_mortgage_bank')) ? trim($this->input->post('expect_mortgage_bank')) : null,       //预计按揭银行
            'expect_mortgage_money' => trim($this->input->post('expect_mortgage_money')) ? trim($this->input->post('expect_mortgage_money')) : null,    //预计按揭金额
            'mortgage_type' => trim($this->input->post('mortgage_type')) ? trim($this->input->post('mortgage_type')) : null,                            //按揭组合
            'house_location' => trim($this->input->post('house_location')) ? trim($this->input->post('house_location')) : '',                           //房屋坐落
            'huose_years' => trim($this->input->post('huose_years')) ? trim($this->input->post('huose_years')) : null,                                  //产证年数
            'is_mortgage' => trim($this->input->post('is_mortgage')) ? trim($this->input->post('is_mortgage')) : null,                                  //房屋是否抵押中
            'need_mortgage' => trim($this->input->post('need_mortgage')) ? trim($this->input->post('need_mortgage')) : -1,                              //是否需要贷款，【重要】,
            'flag' => 1,
            'status_wq' => 0,
            'status_yh_tg' => 0,
            'status_yh_aj' => 0,
            'status_gh' => 0
		);
        if($user_info['store_id'])
            $data['store_id'] = $user_info['store_id'];
        //先验证关键数据是否有效
        if(!$data['total_price'] || $data['total_price'] <= 0){
            return $this->fun_fail('总价不能为空!');
        }
        $data['order_num'] = $this->get_order_num();
        $this->db->insert('warrants', $data);
        $warrants_id = $this->db->insert_id();
        //批量新增买方
        $buyers_insert_ = array();
        foreach($buyers as $k => $v){
            $b_insert_ = array(
                'buyer_name' => $v['buyer_name'],
                'buyer_phone' => $v['buyer_phone'],
                'buyer_card' => $v['buyer_card'],
                'warrants_id' => $warrants_id
            );
            $buyers_insert_[] = $b_insert_;
        }
        $this->db->insert_batch('warrants_buyers', $buyers_insert_);
        //批量新增卖方
        $sellers_insert_ = array();
        foreach($sellers as $k => $v){
            $s_insert_ = array(
                'seller_name' => $v['seller_name'],
                'seller_phone' => $v['seller_phone'],
                'seller_card' => $v['seller_card'],
                'warrants_id' => $warrants_id
            );
            $sellers_insert_[] = $s_insert_;
        }
        $this->db->insert_batch('warrants_sellers', $sellers_insert_);
        return $this->fun_success('操作成功',array('warrants_id' => $warrants_id));
	}

	//服务管家 完善内容
    public function submit_warrants($warrants_id, $fw_admin_id){
        $warrants_info_ = $this->db->select()->from('warrants')->where(array('warrants_id' => $warrants_id))->get()->row_array();
        if (!$warrants_info_)
            return $this->fun_fail('权证单不存在!');
        if ($warrants_info_['flag'] != 1 || $warrants_info_['status_wq'] != 0 || $warrants_info_['status_yh_tg'] != 0 || $warrants_info_['status_yh_aj'] != 0 || $warrants_info_['status_gh'] != 0 )
            return $this->fun_fail('权证单状态已变更 不可提交!');
        if ($warrants_info_['fw_admin_id'] != $fw_admin_id)
            return $this->fun_fail('您没有此权证操作权限!');

        $buyers = $this->input->post("buyers");
        $buyers_insert_ = array();
        if(!is_array($buyers)){
            $buyers = json_decode($buyers,true);
        }
        if(!$buyers)
            return $this->fun_fail('买方不能为空!');
        foreach($buyers as $k_ => $v_){
            if(!isset($v_['buyer_name']) || trim($v_['buyer_name']) == "")
                return $this->fun_fail('存在买方姓名为空!');
            if(!isset($v_['buyer_phone']) || trim($v_['buyer_phone']) == "")
                return $this->fun_fail('存在买方电话为空!');
            if(!isset($v_['buyer_card']) || trim($v_['buyer_card']) == "")
                return $this->fun_fail('存在买方身份证为空!');
            $b_insert_ = array(
                'buyer_name' => $v_['buyer_name'],
                'buyer_phone' => $v_['buyer_phone'],
                'buyer_card' => $v_['buyer_card'],
                'warrants_id' => $warrants_id
            );
            $buyers_insert_[] = $b_insert_;
        }

        $sellers = $this->input->post("sellers");
        $sellers_insert_ = array();
        if(!is_array($sellers)){
            $sellers = json_decode($sellers,true);
        }
        if(!$sellers)
            return $this->fun_fail('卖方不能为空!');
        foreach($sellers as $k_ => $v_){
            if(!isset($v_['seller_name']) || trim($v_['seller_name']) == "")
                return $this->fun_fail('存在卖方姓名为空!');
            if(!isset($v_['seller_phone']) || trim($v_['seller_phone']) == "")
                return $this->fun_fail('存在卖方电话为空!');
            if(!isset($v_['seller_card']) || trim($v_['seller_card']) == "")
                return $this->fun_fail('存在卖方身份证为空!');
            $s_insert_ = array(
                'seller_name' => $v_['seller_name'],
                'seller_phone' => $v_['seller_phone'],
                'seller_card' => $v_['seller_card'],
                'warrants_id' => $warrants_id
            );
            $sellers_insert_[] = $s_insert_;
        }

        $data = array(
            'modify_time' => time(),
            'submit_time' => time(),
            'total_price' => trim($this->input->post('total_price')),                                                                               //成交总价
            'deposit' => trim($this->input->post('deposit')) ? trim($this->input->post('deposit')) : null,                                          //首付、定金
            'expect_mortgage_bank' => trim($this->input->post('expect_mortgage_bank')) ? trim($this->input->post('expect_mortgage_bank')) : null,       //预计按揭银行
            'expect_mortgage_money' => trim($this->input->post('expect_mortgage_money')) ? trim($this->input->post('expect_mortgage_money')) : null,    //预计按揭金额
            'mortgage_type' => trim($this->input->post('mortgage_type')) ? trim($this->input->post('mortgage_type')) : null,                            //按揭组合
            'house_location' => trim($this->input->post('house_location')) ? trim($this->input->post('house_location')) : '',                           //房屋坐落
            'huose_years' => trim($this->input->post('huose_years')) ? trim($this->input->post('huose_years')) : null,                                  //产证年数
            'is_mortgage' => trim($this->input->post('is_mortgage')) ? trim($this->input->post('is_mortgage')) : null,                                  //房屋是否抵押中
            'need_mortgage' => trim($this->input->post('need_mortgage')) ? trim($this->input->post('need_mortgage')) : -1,                              //是否需要贷款，【重要】,
            'status_wq' => 1,
        );
        $data['wq_admin_id'] = $this->get_role_admin_id(1);
        $this->db->where(array('warrants_id' => $warrants_id, 'fw_admin_id' => $fw_admin_id))->update('warrants', $data);
        $this->db->where('warrants_id', $warrants_id)->delete('warrants_buyers');
        $this->db->where('warrants_id', $warrants_id)->delete('warrants_sellers');
        $this->db->insert_batch('warrants_buyers', $buyers_insert_);
        $this->db->insert_batch('warrants_sellers', $sellers_insert_);
        return $this->fun_success('操作成功',array('warrants_id' => $warrants_id));

    }



    //赎楼业务列表 私有 共用方法
    private function warrants_list($where, $order_1 = 'a.create_time', $order_2 = 'desc', $page_ = 1, $limit_ = -1){
        $res = array();
        $data['limit'] = $limit_ < 0 ?$this->mini_limit : $limit_;//每页显示多少调数据
        $data['keyword'] = $this->input->post('keyword')?trim($this->input->post('keyword')):null;
        $data['brand_id'] = $this->input->post('brand_id')?trim($this->input->post('brand_id')):null;
        $data['store_id'] = $this->input->post('store_id')?trim($this->input->post('store_id')):null;
        $data['user_id'] = $this->input->post('user_id')?trim($this->input->post('user_id')):null;
        $data['flag'] = $this->input->post('flag') ? trim($this->input->post('flag')) : null; //默认查进行中 取消默认
        $data['status'] = $this->input->post('status') ? trim($this->input->post('status')) : null;
        $data['is_err'] = $this->input->post('is_err') ? trim($this->input->post('is_err')) : null;

        $page = $this->input->post('page')?trim($this->input->post('page')) : $page_;
        $this->db->select('count(DISTINCT a.warrants_id) num');
        $this->db->from('warrants a');
        $this->db->join('warrants_buyers wb', 'a.warrants_id = wb.warrants_id', 'left');
        $this->db->join('warrants_sellers ws', 'a.warrants_id = ws.warrants_id', 'left');
        $this->db->join('users u','a.user_id = u.user_id','left');
        if ($where && $where != array())
            $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('wb.buyer_name', $data['keyword']);
            $this->db->or_like('wb.buyer_card', $data['keyword']);
            $this->db->or_like('ws.seller_card', $data['keyword']);
            $this->db->or_like('ws.seller_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['is_err']){
            //$this->db->where('a.is_err', $data['is_err']);
        }
        if($data['status']){
            //$this->db->where('a.status', $data['status']);
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
        $this->db->select("a.warrants_id,a.order_num,a.total_price,a.total_price,a.house_location,a.flag,
        u.rel_name handle_name,u.mobile handle_mobile,
        u1.rel_name create_name,u1.mobile create_mobile,
        FROM_UNIXTIME(a.create_time) create_date_,
       a.status_wq,a.status_yh_tg,a.status_yh_aj,a.status_gh,a.need_mortgage,
        fw.admin_name fw_name,fw.phone fw_phone,
        yh.admin_name yh_name,yh.phone yh_phone,
        wq.admin_name wq_name,wq.phone wq_phone,
        gh.admin_name gh_name,gh.phone gh_phone,
         bd.brand_name,s.store_id,s.store_name");
        $this->db->from('warrants a');
        $this->db->join('warrants_buyers wb', 'a.warrants_id = wb.warrants_id', 'left');
        $this->db->join('warrants_sellers ws', 'a.warrants_id = ws.warrants_id', 'left');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('brand_stores s','a.store_id = s.store_id','left');
        $this->db->join('admin fw', 'a.fw_admin_id = fw.admin_id', 'left');
        $this->db->join('admin yh', 'a.yh_admin_id = yh.admin_id', 'left');
        $this->db->join('admin wq', 'a.wq_admin_id = wq.admin_id', 'left');
        $this->db->join('admin gh', 'a.gh_admin_id = gh.admin_id', 'left');
        if ($where && $where != array())
            $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('wb.buyer_name', $data['keyword']);
            $this->db->or_like('wb.buyer_card', $data['keyword']);
            $this->db->or_like('ws.seller_card', $data['keyword']);
            $this->db->or_like('ws.seller_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['is_err']){
           // $this->db->where('a.is_err', $data['is_err']);
        }
        if($data['status']){
           // $this->db->where('a.status', $data['status']);
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
        $this->db->order_by('a.warrants_id', 'desc'); //给个默认排序
        $this->db->group_by('a.warrants_id');
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $res['res_list'] = $this->db->get()->result_array();
        //return $this->db->last_query();
        foreach($res['res_list'] as $k => $v){
            $b_list_ = $this->db->select('group_concat(distinct wb.buyer_name ORDER BY wb.id) b_name_list')->from('warrants_buyers wb')
                ->where(array('wb.warrants_id' => $v['warrants_id']))->get()->row_array();
            $b_name_list_ = $b_list_ ? $b_list_['b_name_list'] : '';
            $res['res_list'][$k]['buyers_list'] = $b_name_list_;

            $s_list_ = $this->db->select('group_concat(distinct ws.seller_name ORDER BY ws.id) s_name_list')->from('warrants_sellers ws')
                ->where(array('ws.warrants_id' => $v['warrants_id']))->get()->row_array();
            $s_name_list_ = $s_list_ ? $s_list_['s_name_list'] : '';
            $res['res_list'][$k]['sellers_list'] = $s_name_list_;
        }
        $res['data'] = $data;
        return $res;
    }

    //赎楼业务详情
    public function warrants_info($loan_id, $select = "*"){
        $select = "a.*,FROM_UNIXTIME(a.create_time) loan_cdate,
        DATE_FORMAT(a.appointment_date,'%Y-%m-%d') appointment_date_handle_,
        DATE_FORMAT(a.redeem_date,'%Y-%m-%d') redeem_date_handle_,
         FROM_UNIXTIME(a.err_time) err_date_,
        FROM_UNIXTIME(a.ww_time) ww_date_,
        FROM_UNIXTIME(a.mx_time) mx_date_,
        FROM_UNIXTIME(a.fk_time) fk_date_,
        FROM_UNIXTIME(a.zs_time) zs_date_,
        FROM_UNIXTIME(a.wq_time) wq_date_,
        FROM_UNIXTIME(a.tg_time) tg_date_,
        FROM_UNIXTIME(a.nj_time) nj_date_,
        FROM_UNIXTIME(a.make_loan_time) make_loan_date_,
        FROM_UNIXTIME(a.gh_time) gh_date_,
        FROM_UNIXTIME(a.returned_money_time) returned_money_date_,
        u.rel_name handle_name,u.mobile handle_mobile,u.invite,
        u1.rel_name create_name,u1.mobile create_mobile,
        mx.admin_name mx_name,mx.phone mx_phone,
        fk.admin_name fk_name,fk.phone fk_phone,
        qz.admin_name qz_name,qz.phone qz_phone,
        fc.admin_name fc_name,fc.phone fc_phone,
        bd.brand_name";
        $this->db->select($select)->from('loan_master a');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('admin mx', 'a.mx_admin_id = mx.admin_id', 'left');
        $this->db->join('admin fk', 'a.fk_admin_id = fk.admin_id', 'left');
         $this->db->join('admin qz', 'a.qz_admin_id = qz.admin_id', 'left');
        $this->db->join('admin fc', 'a.fc_admin_id = fc.admin_id', 'left');
        $loan_info = $this->db->where('a.loan_id', $loan_id)->get()->row_array();
        if(!$loan_info)
            return $this->fun_fail('未找到相关订单!');
        $this->db->select('*');
        $this->db->from('loan_borrowers');
        $this->db->where('loan_id', $loan_id);
        $loan_info['borrowers_list'] = $this->db->get()->result_array();
        $this->db->select("s.id")->from('supervise s');
        $this->db->join('loan_supervise ls','s.id = ls.option_id and ls.loan_id = '. $loan_id,'left');
        $this->db->where('s.status', 1);
        $this->db->where('ls.id', null);
        $check_supervise_ = $this->db->order_by('s.id','asc')->get()->row_array();
        if($check_supervise_ && $loan_id == 1){
            $loan_info['need_supervise_'] = 1;
        }else{
            $loan_info['need_supervise_'] = -1;
        }
        if($loan_info['brand_name'] == ''){
            $loan_info['brand_name'] = '其他(' .$loan_info['other_brand'] . ')';
        }
        return $this->fun_success('获取成功!', $loan_info);
	}

    //单独获取借款人信息
    public function warrants_sellers_info($b_id){
        $this->db->select('a.brand_id, a.status, a.flag, a.user_id, a.mx_admin_id, a.fk_admin_id, a.qz_admin_id,b.*')->from('loan_master a');
        $this->db->join('loan_borrowers b','a.loan_id = b.loan_id','left');
        $this->db->where('b.id', $b_id);
        $info_ = $this->db->get()->row_array();
        if($info_){
            return $this->fun_success('获取成功!', $info_);
        }else{
            return $this->fun_fail('信息不存在!');
        }

    }

    //管理员审核操作记录
    private function save_loan_log4admin($loan_id, $admin_id, $action_type){
        $check_status_ = $this->db->select('status')->from('loan_master')->where('loan_id', $loan_id)->get()->row_array();
        if($check_status_){
            $insert_= array(
                'admin_id' => $admin_id,
                'loan_id' => $loan_id,
                'action_type' => $action_type,
                'status' => $check_status_['status'],
                'cdate' => time()
            );
            $this->db->insert('loan_log', $insert_);
        }
    }

    //管理员审核流程判断
    /*
     * param $warrants_id 代表需要审核的单号
     * param $status_type 代表需要审核的工作流 直接使用栏位名称标注，例如 status_wq 就是网签工作流
     * param $status_value 代表审核所指向的节点
     * param $admin_id 代表审核人
     * */
    private function audit_warrants($warrants_id, $status_type, $status_value, $admin_id, $remark = ''){
        //DBY_problem 需要维护好 审核的流程规划
        // 先网签审核，在网签通过后 即status_wq = 2时，才可以进入银行托管流程，status_yh_tg才可以是1
        // 当need_mortgage为一时，才存在按揭流程，也就意味着 托管流程 status_yh_tg才可以是1 才会存在2的节点
    }

    /**
     *********************************************************************************************
     * 以下代码为管理员端 专用
     *********************************************************************************************
     */

    public function warrants_list4manager($page = 1, $where = array()){
        $data = $this->warrants_list($where, 'a.create_time', 'desc', $page, $this->limit);
        return $data;
    }

    //修改工作流节点,在修改时判断上一节点是否完成 且只能修改进行中的赎楼单
    public function status_change4loan($admin_id,$role_id){
        $loan_id = $this->input->post('loan_id');
        if(!$loan_id || $loan_id <= 0)
            return $this->fun_fail('未传入必要信息!');
        $loan_info = $this->db->select("flag,status,fk_admin_id,mx_time,fk_time,zs_time,has_wq,has_tg,has_nj,has_make_loan,has_gh")->from("loan_master")->where('loan_id', $loan_id)->get()->row_array();
        if(!$loan_info)
            return $this->fun_fail('申请单不存在!');
        if($loan_info['flag'] != 1){
            return $this->fun_fail('此赎楼申请单不在进行中,不可修改工作流!');
        }
        $status = $this->input->post('status');
        if(!$status || !in_array($status, array(1,2,3,4,5,6,7,8,9)))
            return $this->fun_fail('未传入合法的工作流!');
        switch($status){
            case 1:
                break;
            case 2:
                if(!$loan_info['mx_time'])
                    return $this->fun_fail('未完成面签审核,不可直接修改为风控!');
                break;
            case 3:
                if(!$loan_info['fk_time'])
                    return $this->fun_fail('未完成风控审核,不可直接修改为终审!');
                break;
            case 4:
                if(!$loan_info['zs_time'])
                    return $this->fun_fail('未完成终审审核,不可直接修改为待网签!');
                break;
            case 5:
                if($loan_info['has_wq'] != 1)
                    return $this->fun_fail('网签未通过,不可直接修改 待托管!');
                break;
            case 6:
                if($loan_info['has_tg'] != 1)
                    return $this->fun_fail('托管未通过,不可直接修改 待按揭放款!');
                break;
            case 7:
                if($loan_info['has_nj'] != 1)
                    return $this->fun_fail('按揭放款未通过,不可直接修改 待赎楼借款放款!');
                break;
            case 8:
                if($loan_info['has_make_loan'] != 1)
                    return $this->fun_fail('赎楼借款放款未通过,不可直接修改 待过户!');
                break;
            case 9:
                if($loan_info['has_gh'] != 1)
                    return $this->fun_fail('过户未通过,不可直接修改 待回款!');
                break;
            default:
                break;

        }
        $this->db->where(array('loan_id' => $loan_id, 'flag' => 1))->update('loan_master', array('status' => $status));
        return $this->fun_success('操作成功!');
    }
}