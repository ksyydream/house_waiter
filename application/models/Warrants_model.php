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


    //新建权证业务 客户端专用
    public function save_warrants($user_id){

        $buyers = $this->input->post("buyers");;
        if(!is_array($buyers)){
            $buyers = json_decode($buyers,true);
        }

        $sellers = $this->input->post("sellers");;
        if(!is_array($sellers)){
            $sellers = json_decode($sellers,true);
        }

        $check_b_s_ = $this->check_b_s($buyers, $sellers);
        if($check_b_s_['status'] == -1)
            return $this->fun_fail($check_b_s_['msg']);

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

        //先验证关键数据是否有效
        if(!$data['total_price'] || $data['total_price'] <= 0){
            return $this->fun_fail('总价不能为空!');
        }
        $fw_admin_id = $user_info['invite'];
        $data['fw_admin_id'] = $fw_admin_id;
        $data['order_num'] = $this->get_order_num();
        $this->db->insert('warrants', $data);
        $warrants_id = $this->db->insert_id();
        //批量新增买方
        $this->save_b_s($buyers, $sellers, $warrants_id);
        return $this->fun_success('操作成功',array('warrants_id' => $warrants_id));
	}

	//服务管家专用 完善内容
    public function submit_warrants($fw_admin_id, $is_submit = -1){
        $warrants_id = $this->input->post('warrants_id');
        if($warrants_id){
            $warrants_info_ = $this->db->select()->from('warrants')->where(array('warrants_id' => $warrants_id))->get()->row_array();
            if (!$warrants_info_)
                return $this->fun_fail('权证单不存在!');
            if ($warrants_info_['flag'] != 1 || $warrants_info_['status_wq'] != 0 || $warrants_info_['status_yh_tg'] != 0 || $warrants_info_['status_yh_aj'] != 0 || $warrants_info_['status_gh'] != 0 )
                return $this->fun_fail('权证单状态已变更 不可提交!');
            if ($warrants_info_['fw_admin_id'] != $fw_admin_id)
                return $this->fun_fail('您没有此权证操作权限!');
        }

        $buyers = $this->input->post("buyers");
        if(!is_array($buyers)){
            $buyers = json_decode($buyers,true);
        }
        $sellers = $this->input->post("sellers");
        if(!is_array($sellers)){
            $sellers = json_decode($sellers,true);
        }

        $qualification_arr_ = $this->input->post('qualification');
        $qualification_ = '';
        if($qualification_arr_ && is_array($qualification_arr_))
            $qualification_ =  implode(',', $qualification_arr_);
        $data = array(
            'create_time' => time(),
            'modify_time' => time(),
            'total_price' => trim($this->input->post('total_price')),                                                                               //成交总价
            'qualification' => $qualification_,                      //购房资格
            'housing_area' => trim($this->input->post('housing_area')) ? trim($this->input->post('housing_area')) : null,                           //房屋面积
            'mortgage_bank_id' => trim($this->input->post('mortgage_bank_id')) ? trim($this->input->post('mortgage_bank_id')) : null,       //预计按揭银行ID
            'expect_mortgage_money' => trim($this->input->post('mortgage_money')) ? trim($this->input->post('mortgage_money')) : null,    //预计按揭金额
            'mortgage_money' => trim($this->input->post('mortgage_money')) ? trim($this->input->post('mortgage_money')) : null,    //预计按揭金额
            'mortgage_type' => trim($this->input->post('mortgage_type')) ? trim($this->input->post('mortgage_type')) : null,                            //按揭组合
            'house_location' => trim($this->input->post('house_location')) ? trim($this->input->post('house_location')) : '',                           //房屋坐落
            'huose_years' => trim($this->input->post('huose_years')) ? trim($this->input->post('huose_years')) : null,                                  //产证年数
            'is_mortgage' => trim($this->input->post('is_mortgage')) ? trim($this->input->post('is_mortgage')) : null,                                  //房屋是否抵押中
            'need_mortgage' => trim($this->input->post('need_mortgage')) ? trim($this->input->post('need_mortgage')) : -1,                              //是否需要贷款，【重要】,
            'house_num' => trim($this->input->post('house_num')) ? trim($this->input->post('house_num')) : null,                                  //第几套房子
            'tax' => trim($this->input->post('tax')) ? trim($this->input->post('tax')) : null,                                  //契税点数
            'is_local' => trim($this->input->post('is_local')) ? trim($this->input->post('is_local')) : null,
            'remark' => trim($this->input->post('remark')) ? trim($this->input->post('remark')) : null,
            'user_type' => trim($this->input->post('user_type')) ? trim($this->input->post('user_type')) : null,
            'flag' => 1,
            'status_wq' => 0,
            'status_yh_tg' => 0,
            'status_yh_aj' => 0,
            'status_gh' => 0
        );
        $data['wq_admin_id'] = trim($this->input->post('wq_admin_id'));
        $data['fw_admin_id'] = $fw_admin_id;

        //一些栏位 必须验证
        if (!$data['user_type'] || !in_array($data['user_type'], array(1,2)))
            return $this->fun_fail('客户类型必须选择!');
        switch ($data['user_type']){
            case 1:
                $data['user_id'] = trim($this->input->post('user_id'));
                break;
            case 2:
                $data['user_phone'] = trim($this->input->post('user_phone'));
                $data['temp_rel_name'] = trim($this->input->post('temp_rel_name'));
                break;
        }
        switch ($data['need_mortgage']){
            case 1:
                if ($data['mortgage_bank_id']){
                    $bank_info_ = $this->db->select('*')->from('bank')->where(array('id' => $data['mortgage_bank_id'], 'status' => 1))->get()->row_array();
                    if($bank_info_){
                        $data['expect_mortgage_bank'] = $bank_info_['name'];
                        $data['mortgage_bank'] = $bank_info_['name'];
                    }
                }
                break;
            case -1:
                $data['expect_mortgage_money'] = '';
                $data['mortgage_money'] = '';
                $data['expect_mortgage_bank'] = '';
                $data['mortgage_bank'] = '';
                break;
            default:
                return $this->fun_fail('是否需要按揭标记 必须传递!');
        }
        //如果确认提交 则需要进行所有的验证
        if($is_submit == 1){
            $data['status_wq'] = 1;
            $data['submit_time'] = time();
            switch ($data['user_type']){
                case 1:
                    $data['user_id'] = trim($this->input->post('user_id'));
                    if (!$data['user_id'])
                        return $this->fun_fail('门店客户必须指定经纪人!');
                    $user_info_ = $this->db->select('*')->from('users')->where(array('user_id' => $data['user_id'], 'status' => 1))->get()->row_array();
                    if(!$user_info_)
                        return $this->fun_fail('所选经纪人不可用!');
                    $data['create_user_id'] = $user_info_['user_id'];
                    $data['user_phone'] = $user_info_['mobile'];
                    $data['create_user_phone'] = $user_info_['mobile'];
                    $data['temp_rel_name'] = $user_info_['rel_name'];
                    $data['brand_id'] = $user_info_['brand_id'];
                    $data['store_id'] = $user_info_['store_id'];
                    break;
                case 2:
                    if (!$data['user_phone'])
                        return $this->fun_fail('个人客户 需要填写手机号!');
                    if (!$data['temp_rel_name'])
                        return $this->fun_fail('个人客户 需要填写姓名!');
                    break;
            }
            if (!$data['wq_admin_id'])
                return $this->fun_fail('网签人员未设置!');
            if (!$data['total_price'] || $data['total_price'] == 0)
                return $this->fun_fail('成交总价必填!');
            if (!$data['house_location'])
                return $this->fun_fail('房屋坐落必填!');
            if (!$data['housing_area'])
                return $this->fun_fail('房屋面积必填!');
            if (!$data['mortgage_type'])
                return $this->fun_fail('按揭组合未设置!');
            if (!$data['is_mortgage'] || !in_array($data['is_mortgage'], array(1,-1)))
                return $this->fun_fail('是否在押必须选择!');
            switch ($data['need_mortgage']){
                case 1:
                    if (!$data['mortgage_bank_id'])
                        return $this->fun_fail('目标按揭银行必须选择!');
                    if (!$data['mortgage_money'] || $data['mortgage_money'] <= 0)
                        return $this->fun_fail('按揭金额必须填写!');
                    $bank_info_ = $this->db->select('*')->from('bank')->where(array('id' => $data['mortgage_bank_id'], 'status' => 1))->get()->row_array();
                    if(!$bank_info_)
                        return $this->fun_fail('所选银行不可用!');
                    break;
                case -1:
                    break;
                default:
                    return $this->fun_fail('是否需要按揭标记 必须传递!');
            }
            if (!$data['qualification'])
                return $this->fun_fail('购房资格必须选择!');
            //if (!$data['huose_years'])
                //return $this->fun_fail('产证年数必须填写!');
            //if (!$data['house_num'])
                //return $this->fun_fail('第几套房必须填写!');
            //if (!$data['tax'])
                //return $this->fun_fail('契税点数必须填写!');
            $check_b_s_ = $this->check_b_s($buyers, $sellers);
            if($check_b_s_['status'] == -1)
                return $this->fun_fail($check_b_s_['msg']);
        }
        if ($warrants_id){
            $this->db->where(array('warrants_id' => $warrants_id, 'fw_admin_id' => $fw_admin_id))->update('warrants', $data);
        }else{
            $data['order_num'] = $this->get_order_num();
            $this->db->insert('warrants', $data);
            $warrants_id = $this->db->insert_id();
        }
        $this->save_b_s($buyers, $sellers, $warrants_id);
        return $this->fun_success('操作成功',array('warrants_id' => $warrants_id));

    }

    //验证 买卖双方信息数组
    private function check_b_s($buyers = array(), $sellers = array()){
        if(!$buyers || $buyers == array())
            return $this->fun_fail('买方不能为空!');
        foreach($buyers as $k_ => $v_){
            if(!isset($v_['buyer_name']) || trim($v_['buyer_name']) == "")
                return $this->fun_fail('存在买方姓名为空!');
            if(!isset($v_['buyer_phone']) || trim($v_['buyer_phone']) == "")
                return $this->fun_fail('存在买方电话为空!');
            if(!isset($v_['buyer_card']) || trim($v_['buyer_card']) == "")
                return $this->fun_fail('存在买方身份证为空!');
            if(!isset($v_['buyer_marriage']) || trim($v_['buyer_marriage']) == "")
                return $this->fun_fail('存在买方婚姻状况为空!');
        }
        if(!$sellers || $sellers == array())
            return $this->fun_fail('卖方不能为空!');
        foreach($sellers as $k_ => $v_){
            if(!isset($v_['seller_name']) || trim($v_['seller_name']) == "")
                return $this->fun_fail('存在卖方姓名为空!');
            if(!isset($v_['seller_phone']) || trim($v_['seller_phone']) == "")
                return $this->fun_fail('存在卖方电话为空!');
            if(!isset($v_['seller_card']) || trim($v_['seller_card']) == "")
                return $this->fun_fail('存在卖方身份证为空!');
            if(!isset($v_['seller_marriage']) || trim($v_['seller_marriage']) == "")
                return $this->fun_fail('存在卖方婚姻状况为空!');
        }
        return $this->fun_success('验证成功');
    }

    //批量处理 买卖方
    private function save_b_s($buyers = array(), $sellers = array(), $warrants_id)
    {
        $this->db->where('warrants_id', $warrants_id)->delete('warrants_buyers');
        $this->db->where('warrants_id', $warrants_id)->delete('warrants_sellers');
        if ($buyers) {
            $buyers_insert_ = array();
            foreach ($buyers as $k => $v) {
                $b_insert_ = array(
                    'buyer_name' => isset($v['buyer_name']) ? $v['buyer_name'] : '',
                    'buyer_phone' =>  isset($v['buyer_phone']) ? $v['buyer_phone'] : '',
                    'buyer_card' =>  isset($v['buyer_card']) ? $v['buyer_card'] : '',
                    'buyer_marriage' =>  isset($v['buyer_marriage']) ? $v['buyer_marriage'] : '',
                    'warrants_id' => $warrants_id
                );
                $buyers_insert_[] = $b_insert_;
            }
            $this->db->insert_batch('warrants_buyers', $buyers_insert_);
        }
        if ($sellers) {
            $sellers_insert_ = array();
            foreach ($sellers as $k => $v) {
                $s_insert_ = array(
                    'seller_name' => $v['seller_name'],
                    'seller_phone' => $v['seller_phone'],
                    'seller_card' => $v['seller_card'],
                    'seller_marriage' => $v['seller_marriage'],
                    'warrants_id' => $warrants_id
                );
                $sellers_insert_[] = $s_insert_;
            }
            $this->db->insert_batch('warrants_sellers', $sellers_insert_);
        }
        return $this->fun_success('操作成功');
    }



    //权证业务列表 私有 共用方法
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
       a.status_wq,a.status_yh,a.status_gh,a.need_mortgage,
        fw.admin_name fw_name,fw.phone fw_phone,
        yh_tg.admin_name yh_tg_name,yh_tg.phone yh_tg_phone,
        yh_aj.admin_name yh_aj_name,yh_aj.phone yh_aj_phone,
        wq.admin_name wq_name,wq.phone wq_phone,
        gh.admin_name gh_name,gh.phone gh_phone,
         gh_yy.admin_name gh_yy_name,gh_yy.phone gh_yy_phone,
         bd.brand_name,s.store_id,s.store_name");
        $this->db->from('warrants a');
        $this->db->join('warrants_buyers wb', 'a.warrants_id = wb.warrants_id', 'left');
        $this->db->join('warrants_sellers ws', 'a.warrants_id = ws.warrants_id', 'left');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('brand_stores s','a.store_id = s.store_id','left');
        $this->db->join('admin fw', 'a.fw_admin_id = fw.admin_id', 'left');
        $this->db->join('admin yh_tg', 'a.yh_tg_admin_id = yh_tg.admin_id', 'left');
        $this->db->join('admin yh_aj', 'a.yh_aj_admin_id = yh_aj.admin_id', 'left');
        $this->db->join('admin wq', 'a.wq_admin_id = wq.admin_id', 'left');
        $this->db->join('admin gh_yy', 'a.gh_yy_admin_id = gh_yy.admin_id', 'left');
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

    //权证业务详情
    public function warrants_info($warrants_id, $select_ = "*"){
        $select = "a.*,FROM_UNIXTIME(a.create_time) warrants_cdate,
        FROM_UNIXTIME(a.submit_time) submit_cdate_,
       u.rel_name handle_name,u.mobile handle_mobile,
        u1.rel_name create_name,u1.mobile create_mobile,
         fw.admin_name fw_name,fw.phone fw_phone,
        yh_tg.admin_name yh_tg_name,yh_tg.phone yh_tg_phone,
        yh_aj.admin_name yh_aj_name,yh_aj.phone yh_aj_phone,
        wq.admin_name wq_name,wq.phone wq_phone,
        gh.admin_name gh_name,gh.phone gh_phone,
         gh_yy.admin_name gh_yy_name,gh_yy.phone gh_yy_phone,
         bd.brand_name,s.store_id,s.store_name";
        $this->db->select($select);
        $this->db->from('warrants a');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('brand_stores s','a.store_id = s.store_id','left');
        $this->db->join('admin fw', 'a.fw_admin_id = fw.admin_id', 'left');
        $this->db->join('admin yh_tg', 'a.yh_tg_admin_id = yh_tg.admin_id', 'left');
        $this->db->join('admin yh_aj', 'a.yh_aj_admin_id = yh_aj.admin_id', 'left');
        $this->db->join('admin wq', 'a.wq_admin_id = wq.admin_id', 'left');
        $this->db->join('admin gh_yy', 'a.gh_yy_admin_id = gh_yy.admin_id', 'left');
        $this->db->join('admin gh', 'a.gh_admin_id = gh.admin_id', 'left');
        $warrants_info = $this->db->where('a.warrants_id', $warrants_id)->get()->row_array();
        if(!$warrants_info)
            return $this->fun_fail('未找到相关申请!');
        $this->db->select('*');
        $this->db->from('warrants_buyers');
        $this->db->where('warrants_id', $warrants_id);
        $warrants_info['buyers_list'] = $this->db->get()->result_array();
        $this->db->select('*');
        $this->db->from('warrants_sellers');
        $this->db->where('warrants_id', $warrants_id);
        $warrants_info['sellers_list'] = $this->db->get()->result_array();
        return $this->fun_success('获取成功!', $warrants_info);
	}

	private function warrants_count_where($flag, $status_wq, $status_yh, $status_gh, $admin_id, $need_choice_admin = -1){

    }

    public function warrants_count($admin_id, $role_id){
        //待网签审核
        $wq_1_num = $this->db->select('count(1) num')->from('warrants')->where(
            array('flag' => 1, 'status_wq' => 1,'need_choice_admin' => -1, 'wq_admin_id' => $admin_id)
        )->get()->row();
        //待政审，网签完成
        $wq_2_num = $this->db->select('count(1) num')->from('warrants')->where(
            array('flag' => 1, 'status_wq' => 2,'need_choice_admin' => -1, 'wq_admin_id' => $admin_id)
        )->get()->row();
        //带首付/全款 托管
        $yh_1_num = $this->db->select('count(1) num')->from('warrants')->where(
            array('flag' => 1, 'status_yh' => 2,'need_choice_admin' => -1, 'yh_tg_admin_id' => $admin_id)
        )->get()->row();

        $yh_2_num = $this->db->select('count(1) num')->from('warrants')->where(
            array('flag' => 1, 'status_yh' => 2,'need_choice_admin' => -1, 'yh_tg_admin_id' => $admin_id)
        )->get()->row();
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