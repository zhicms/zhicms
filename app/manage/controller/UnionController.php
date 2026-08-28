<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class UnionController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /**
     * 联盟库(api接口) 首页：API 实时选品 + 一键采集入库
     * 展示大淘客/好单库等联盟商品，支持单条采集或一键更新写入本地选品库(yun_items)。
     * 预留拼多多/唯品会/京东：见 getPlatforms() 中的 enabled=false 占位。
     */
    public function index(){

        $this->checkManageSession();

        $this->pageText = array("联盟库", "API接口选品");

        // 分类下拉（与选品库共用全站分类）
        $this->categories = $this->getGoodsCategories();

        $baseUrl = "index.php?r=manage/union/index";

        $page = intval($this->arg("page", 1));
        $pageSize = intval($this->arg("pageSize", 200));

        $platform = strtolower($this->arg("platform", 'taobao'));
        if($platform){
            $baseUrl .= "&platform={$platform}";
        }

        $keyword = $this->arg("keyword", '');
        if($keyword){
            $baseUrl .= "&keyword=" . urlencode($keyword);
        }

        $cid = $this->arg("cid", '');
        if($cid){
            $baseUrl .= "&cid={$cid}";
        }

        $sort = $this->arg("sort", '');
        if($sort){
            $baseUrl .= "&sort={$sort}";
        }

        $hasCoupon = $this->arg("hasCoupon", '');
        if($hasCoupon !== ''){
            $baseUrl .= "&hasCoupon={$hasCoupon}";
        }

        $client = $this->createTjkClient();
        $items = [];
        $total = 0;
        $isCompare = ($platform === 'compare');
        $this->isCompare = $isCompare;

        if ($client) {
            if ($isCompare) {
                // ===== 比价模式：同时搜索淘宝 / 京东 / 拼多多，按同款聚合对比价格 =====
                if (!empty($keyword)) {
                    $all = [];

                    // 淘宝（大淘客 + 好单库淘宝 合并）
                    $tbResp = $client->searchGoods($keyword, 'taobao', 1, $pageSize);
                    if ($tbResp['code'] == 1 && !empty($tbResp['items'])) {
                        $all = array_merge($all, $tbResp['items']);
                    }

                    // 京东 / 拼多多（好单库）
                    if ($client->getHdk()) {
                        $jdResp = $client->searchGoods($keyword, 'jd', 1, $pageSize, 1, $sort, $hasCoupon);
                        if ($jdResp['code'] == 1 && !empty($jdResp['items'])) {
                            $all = array_merge($all, $jdResp['items']);
                        }
                        $pddResp = $client->searchGoods($keyword, 'pdd', 1, $pageSize, 1, $sort, $hasCoupon);
                        if ($pddResp['code'] == 1 && !empty($pddResp['items'])) {
                            $all = array_merge($all, $pddResp['items']);
                        }
                    }

                    if (!empty($all)) {
                        $items = $this->aggregateSameItems($all);
                        $total = count($items);
                    }
                }
            } elseif (!empty($keyword)) {
                if ($platform === 'taobao') {
                    // 淘宝：大淘客 + 好单库(淘宝) 合并
                    $response = $client->searchGoods($keyword, 'dtk', $page, $pageSize);
                    if ($response['code'] == 1 && !empty($response['items'])) {
                        $items = $response['items'];
                        $total = $response['total'] ?? 0;
                    }

                    $hdkResponse = $client->searchGoods($keyword, 'hdk', $page, $pageSize);
                    if ($hdkResponse['code'] == 1 && !empty($hdkResponse['items'])) {
                        $items = array_merge($items, $hdkResponse['items']);
                        $total += $hdkResponse['total'] ?? 0;
                    }
                } else {
                    // 拼多多 / 京东 / 唯品会：走好单库对应搜索接口
                    $response = $client->searchGoods($keyword, $platform, $page, $pageSize, 1, $sort, $hasCoupon);
                    if ($response['code'] == 1 && !empty($response['items'])) {
                        $items = $response['items'];
                        $total = $response['total'] ?? 0;
                    } else {
                        // 记录真实错误，避免误导“请先配置 API 密钥”
                        $this->apiError = $response['message'] ?? '搜索接口未返回数据';
                    }
                }

                usort($items, function($a, $b) {
                    return ($b['monthSales'] ?? 0) - ($a['monthSales'] ?? 0);
                });

                $items = array_slice($items, 0, $pageSize);
            } else {
                // 无关键词浏览模式
                if ($platform === 'taobao') {
                    // 淘宝：使用 get-goods-list 全量商品列表（返回完整字段含标题/主图），替代定时拉取
                    // sort 映射：get-goods-list API 排序值: 0=综合, 1=券后价升序, 2=券后价降序, 3=月销量降序
                    $apiSort = '0';
                    if ($sort === '5') {
                        $apiSort = '2';     // 价格高到低 → 券后价降序
                    } elseif ($sort === '6') {
                        $apiSort = '1';     // 价格低到高 → 券后价升序
                    } elseif ($sort === '2') {
                        $apiSort = '3';     // 热销高到低 → 月销量降序
                    }

                    $extra = [
                        'cid'  => $cid,
                        'sort' => $apiSort,
                    ];

                    $response = $client->getGoodsList($pageSize, strval($page), $extra, true);

                    if ($response['code'] == 1 && !empty($response['items'])) {
                        $items = $response['items'];
                        $total = $response['total'] ?? 0;
                    } else {
                        // 将API错误信息传到视图，便于排查
                        $this->apiError = $response['message'] ?? 'API未返回数据';
                    }
                } else {
                    // 京东/拼多多/唯品会：无关键词时按分类/综合拉取第一页商品（与前端行为一致）
                    $response = $client->searchGoods('', $platform, $page, $pageSize, 1, $sort, $hasCoupon);
                    if ($response['code'] == 1 && !empty($response['items'])) {
                        $items = $response['items'];
                        $total = $response['total'] ?? count($items);
                    } else {
                        $this->apiError = $response['message'] ?? '该平台暂无可浏览商品';
                    }
                }
            }

        }

        // 加载已入库的 goodsId 集合（用于前端标记"已入库"状态，不再过滤隐藏）
        $dbGoodsIds = [];
        if (!$isCompare) {
            $dbGoodsIds = $this->getSelectionIds();
        }

        // 使用 API 返回的总数做分页，不因过滤而破坏分页
        $totalPages = ($total > 0) ? ceil($total / $pageSize) : 0;

        $pages = '';
        if ($totalPages > 1) {
            $pages .= '<a href="' . $baseUrl . '&page=1">首页</a>';
            if ($page > 1) {
                $pages .= '<a href="' . $baseUrl . '&page=' . ($page - 1) . '">上一页</a>';
            }
            for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
                if ($i == $page) {
                    $pages .= '<span class="current">' . $i . '</span>';
                } else {
                    $pages .= '<a href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a>';
                }
            }
            if ($page < $totalPages) {
                $pages .= '<a href="' . $baseUrl . '&page=' . ($page + 1) . '">下一页</a>';
            }
            $pages .= '<a href="' . $baseUrl . '&page=' . $totalPages . '">尾页</a>';
        }

        $this->page = [
            'list' => $items,
            'pages' => $pages,
            'total' => $total,
        ];
        $this->Page = $this->page;
        $this->platform = $platform;
        $this->dbGoodsIds = $dbGoodsIds;  // 已入库商品ID集合，视图用于标记"已入库"状态

        $this->display('app/manage/view/union/index');
    }

    /**
     * 获取数据库选品库(yun_items)中已存在的商品ID集合，用于 API 列表去重
     */
    private function getSelectionIds() {
        $ids = [];
        $rows = obj('api/ApiData')->thisQuery("SELECT `goodsId` FROM `{pre}items` WHERE `goodsId` != ''");
        if (!empty($rows)) {
            foreach ($rows as $row) {
                if (!empty($row['goodsId'])) {
                    $ids[$row['goodsId']] = true;
                }
            }
        }
        return $ids;
    }

    // ===================== 联盟模型（API 密钥配置） =====================

    /**
     * 联盟模型列表
     */
    public function union(){
        $this->pageText = array("联盟模型", "联盟模型");
        $where[] = "1";
        $baseUrl = "index.php?r=manage/union/union";
        $page = obj('api/ApiData')->page("50", "yun_union", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
        $this->display('app/manage/view/union/union');
    }

    public function addUnion(){

        $this->checkManageSession();

        if (!\IS_POST) {
            $this->pageText = array("联盟模型", "新建联盟模型");
            $this->display('app/manage/view/union/addunion');
            exit;
        } else {
            $data = obj('api/Api')->Form($this->POSTarg());
            $data['code'] = base64_encode($_POST['code']);
            obj('api/ApiData')->insertData('yun_union', $data);
            echo json_encode(array("info" => "保存成功", "status" => "y"));
        }
    }

    public function editorUnion(){

        $this->checkManageSession();

        if (!\IS_POST) {
            $this->pageText = array("联盟模型", "编辑联盟模型");
            $id = intval($this->arg("id"));
            $where['id'] = $id;
            $ret = obj("api/ApiData")->dataSelect("yun_union", $where);
            $this->ret = $ret;
            $this->html = '<input type="hidden" name="id" value="' . $ret['id'] . '" />';
            $this->display('app/manage/view/union/addunion');
            exit;
        } else {
            $id = intval($this->arg("id"));
            $where['id'] = $id;
            $data = obj('api/Api')->Form($this->POSTarg());
            $data['code'] = base64_encode($_POST['code']);
            obj("api/ApiData")->dataUpdate("yun_union", $data, $where);
            echo json_encode(array("info" => "保存成功", "status" => "y"));
        }
    }

    public function deleteUnion(){

        $this->checkManageSession();

        error_reporting(0);
        $id = intval($this->arg("id"));
        $where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_union', $where, array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }

    // 加载联盟模型（供商城表单使用）
    public function loadUnion(){

        $this->checkManageSession();

        $where[] = "1";
        $ret = obj("api/ApiData")->dataSelect("yun_union", $where,"`id` DESC");
        return $ret;
    }

    // ===================== 商城管理 =====================

    /**
     * 商城管理
     */
    public function mall(){

        $this->checkManageSession();

        $this->pageText = array("商城管理", "管理商城");
        $where[] = "1";
        $baseUrl = "index.php?r=manage/union/mall";
        $page = obj('api/ApiData')->page("50", "yun_mall", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
        $this->display('app/manage/view/union/mall');
    }

    public function addMall(){

        $this->checkManageSession();

        if (!\IS_POST) {
            $this->pageText = array("商城管理", "添加商城");
            $this->union = self::loadUnion();
            $this->display('app/manage/view/union/addmall');
            exit;
        } else {
            self::checkMallForm();
            $data = obj('api/Api')->Form($this->POSTarg());
            obj('api/ApiData')->insertData('yun_mall', $data);
            echo json_encode(array("info" => "保存成功", "status" => "y"));
        }
    }

    public function editorMall(){

        $this->checkManageSession();

        if (!\IS_POST) {
            $this->pageText = array("商城管理", "编辑商城");
            $id = intval($this->arg("id"));
            $where['id'] = $id;
            $ret = obj("api/ApiData")->dataSelect("yun_mall", $where);
            $this->ret = $ret;
            $this->html = '<input type="hidden" name="id" value="' . $ret['id'] . '" />';
            $this->union = self::loadUnion();
            $this->display('app/manage/view/union/addmall');
            exit;
        } else {
            self::checkMallForm();
            $id = intval($this->arg("id"));
            $where['id'] = $id;
            $data = obj('api/Api')->Form($this->POSTarg());
            obj("api/ApiData")->dataUpdate("yun_mall", $data, $where);
            echo json_encode(array("info" => "保存成功", "status" => "y"));
        }
    }

    public function deleteMall(){

        $this->checkManageSession();

        error_reporting(0);
        $id = intval($this->arg("id"));
        $where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_mall', $where, array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }

    public function checkMallForm(){
        $this->checkManageSession();

        if(!$this->arg("name")){
            exit(json_encode(array("info" => "请填写商城名称", "status" => "n")));
        }
    }

    // ===================== 版块管理 =====================

    /**
     * 版块管理（yun_bankuai 列表 / yun_group 分类维护）
     */
    public function type(){

        $this->checkManageSession();

        $this->pageText = array("版块管理", "管理天猫淘宝宝贝版块");
        $where[] = "1";
        $baseUrl = "index.php?r=manage/union/type";
        $page = obj('api/ApiData')->page("50", "yun_bankuai", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
        $this->display('app/manage/view/union/type');
    }

    public function addType(){

        $this->checkManageSession();

        if(!\IS_POST){
            $this->pageText = array("版块管理", "新建版块");
            $this->display('app/manage/view/union/addtype');
            exit;
        }else{
            self::checkTypeForm();
            $data = obj('api/Api')->Form($this->POSTarg());
            // 统一操作 yun_bankuai（与 type() 列表一致）
            $data['px'] = isset($data['px']) && $data['px'] !== '' ? intval($data['px']) : 0;
            obj('api/ApiData')->insertData('yun_bankuai', $data);
            exit(json_encode(array("info" => "保存成功", "status" => "y")));

        }
    }

    public function editorType(){

        $this->checkManageSession();

        if(!\IS_POST){
            $this->pageText = array("版块管理", "编辑版块");
            $id = intval($this->arg("id"));
            $where['id'] = $id;
            $ret = obj("api/ApiData")->dataSelect("yun_bankuai",$where);
            $this->ret = $ret;
            $this->html = '<input type="hidden" name="id" value="'.$ret['id'].'" />';
            $this->display('app/manage/view/union/addtype');
            exit;
        }else{
            self::checkTypeForm();
            $id = intval($this->arg("id"));
            if ($id <= 0) {
                exit(json_encode(array("info" => "缺少有效的版块 ID", "status" => "n")));
            }
            $where['id'] = $id;
            $data = obj('api/Api')->Form($this->POSTarg());
            unset($data['id']);
            $data['px'] = isset($data['px']) && $data['px'] !== '' ? intval($data['px']) : 0;
            obj("api/ApiData")->dataUpdate("yun_bankuai",$data,$where);
            exit(json_encode(array("info" => "保存成功", "status" => "y")));
        }

    }

    public function deleteType(){

        $this->checkManageSession();

        error_reporting(0);
        $id = intval($this->arg("id"));
        // 检查该版块下是否有帖子（yun_forum.bankuai_id）
        $where['bankuai_id'] = $id;
        $count = obj("api/ApiData")->dataCount("yun_forum", $where);
        if($count>0){
            exit(json_encode(array("info" => "该版块下还有帖子，请先删除", "status" => "n")));
        }

        //删除版块
        $where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_bankuai', $where, array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));

    }

    public function navCount(){

        $this->checkManageSession();

        $id = $this->arg("id");
        $where[] = "`groupid` ={$id}";
        $count = obj("api/ApiData")->dataCount("yun_forum", $where);
        echo $count;
    }

    public function checkTypeForm(){

        $this->checkManageSession();

        if(!$this->arg("name")){
            exit(json_encode(array("info" => "请填写版块名称", "status" => "n")));
        }
        if(!$this->arg("px")){
            exit(json_encode(array("info" => "请填写排序", "status" => "n")));
        }
    }

    // ===================== 多麦（duomai）联盟商品同步 =====================

    public function duoMai(){

        $this->checkManageSession();

        $api = \app\common\ConfigStore::load('api');
        header('Content-Type:text/html; charset=utf-8');
        //1.获取xml数据
        $xmlData = file_get_contents("https://www.duomai.com/api/ads.php?hash=".(isset($api['hash']) ? $api['hash'] : '')."&action=getApplyAds");
        $xmlString = simplexml_load_string($xmlData, 'SimpleXMLElement', LIBXML_NOCDATA);
        $valueArray = json_decode(json_encode($xmlString),true);
        $data = json_encode($valueArray, JSON_UNESCAPED_UNICODE);
        $json = json_decode($data, true);
        $result = array_shift($json);
        $ret = obj("api/Api")->objectArray($json);
        $apiObj = obj('api/ApiData');
        // 事务必须开在【写连接】上：原 thisQuery('BEGIN') 走读连接，写连接一直 autocommit，每条 INSERT 都 fsync（600 条十几秒）
        $apiObj->beginTransaction();
        $count = 0;
        $batch = array();
        $batchSize = 200;
        for($key='0';$key<=100000;$key++){
            $fixKey = 'fix_'.$key;
            if(!isset($ret[$fixKey]) || !is_array($ret[$fixKey])){
                break;
            }
            $item = $ret[$fixKey];
            $shuju['ads_id'] = intval($item['ads_id'] ?? 0);
            if($shuju['ads_id'] <= 0){
                continue;
            }
            $shuju['ads_name'] = htmlspecialchars(mb_substr($item['ads_name'] ?? '', 0, 255), ENT_QUOTES, 'UTF-8');
            $shuju['channel'] = intval($item['channel'] ?? 0);
            $shuju['status'] = intval($item['status'] ?? 0);
            $shuju['applay_mode'] = intval($item['applay_mode'] ?? 0);
            $shuju['hide'] = intval($item['hide'] ?? 0);
            $shuju['type'] = intval($item['type'] ?? 0);
            $shuju['cate_name'] = htmlspecialchars(mb_substr($item['cate_name'] ?? '', 0, 100), ENT_QUOTES, 'UTF-8');
            $shuju['ads_endtime'] = mb_substr($item['ads_endtime'] ?? '', 0, 20);
            $shuju['ads_commission'] = floatval($item['ads_commission'] ?? 0);
            $siteUrl = $item['site_url'] ?? '';
            if(filter_var($siteUrl, FILTER_VALIDATE_URL)){
                $shuju['site_url'] = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');
            }else{
                $shuju['site_url'] = '';
            }
            $siteLogo = $item['site_logo'] ?? '';
            if(filter_var($siteLogo, FILTER_VALIDATE_URL)){
                $shuju['site_logo'] = htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8');
            }else{
                $shuju['site_logo'] = '';
            }
            $shuju['site_description'] = htmlspecialchars(mb_substr($item['site_description'] ?? '', 0, 500), ENT_QUOTES, 'UTF-8');
            $shuju['adser'] = htmlspecialchars(mb_substr($item['adser'] ?? '', 0, 100), ENT_QUOTES, 'UTF-8');
            $shuju['country'] = htmlspecialchars(mb_substr($item['country'] ?? '', 0, 50), ENT_QUOTES, 'UTF-8');
            $batch[] = $shuju;
            $count++;
            // 攒够一批就批量插入（一条多值 INSERT），大幅减少网络往返
            if (count($batch) >= $batchSize) {
                $apiObj->insertAllData("yun_duomai", $batch);
                $batch = array();
            }
        }
        // 插入剩余批次
        if (!empty($batch)) {
            $apiObj->insertAllData("yun_duomai", $batch);
        }
        $apiObj->commit();

        exit(json_encode(array("info" => "同步完成，共导入{$count}条数据", "status" => "y", "total" => $count)));
    }

    public function duoMaiList(){

        $this->checkManageSession();

        header('Content-Type:text/html; charset=utf-8');
        //1.获取xml数据
        $xmlData = file_get_contents("https://www.duomai.com/api/ads.php?hash=355d9878e6469953b0383452533b6f43&action=getHighProductions");
        $xmlString = simplexml_load_string($xmlData, 'SimpleXMLElement', LIBXML_NOCDATA);
        $valueArray = json_decode(json_encode($xmlString),true);
        $data = json_encode($valueArray, JSON_UNESCAPED_UNICODE);
        $json = json_decode($data, true);//再次解析参数获得数组

        exit(strip_tags(json_encode($json, JSON_UNESCAPED_UNICODE)));
    }

    // ===================== API 客户端 / 采集入库 =====================

    /**
     * 比价模式：将来自不同平台（淘宝/京东/拼多多）的商品按"同款"聚合。
     * 匹配依据：标题经归一化后的字符 bigram Jaccard 相似度（涵盖标题/店铺/型号一致），
     * 相似度 ≥ 0.5 视为同款归为一组；组内按各平台最低价对比，整体按最低价升序。
     */
    private function aggregateSameItems(array $items): array {
        $groups = [];
        foreach ($items as $item) {
            $title = trim($item['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $norm = $this->normTitle($title);
            $bestIdx = -1;
            $bestSim = 0.0;
            foreach ($groups as $gi => $g) {
                $sim = $this->titleSim($norm, $g['norm']);
                if ($sim > $bestSim) {
                    $bestSim = $sim;
                    $bestIdx = $gi;
                }
            }
            if ($bestIdx >= 0 && $bestSim >= 0.5) {
                $groups[$bestIdx]['items'][] = $item;
            } else {
                $groups[] = [
                    'norm'     => $norm,
                    'repTitle' => $title,
                    'repPic'   => $item['mainPic'] ?? '',
                    'items'    => [$item],
                ];
            }
        }

        foreach ($groups as &$g) {
            $priceByPlatform = [];
            $minPrice = PHP_FLOAT_MAX;
            foreach ($g['items'] as $it) {
                $pf = $it['item_from'] ?? 'taobao';
                $price = floatval($it['actualPrice'] ?? 0);
                if (!isset($priceByPlatform[$pf]) || $price < $priceByPlatform[$pf]['price']) {
                    $priceByPlatform[$pf] = ['price' => $price, 'item' => $it];
                }
                if ($price < $minPrice) {
                    $minPrice = $price;
                }
            }
            $g['priceByPlatform'] = $priceByPlatform;
            $g['minPrice'] = $minPrice == PHP_FLOAT_MAX ? 0 : $minPrice;
            $g['platformCount'] = count($priceByPlatform);
        }
        unset($g);

        // 排序：最低价升序；价格相同则匹配平台数多的优先（跨平台对比信息更全）
        usort($groups, function($a, $b) {
            if (abs($a['minPrice'] - $b['minPrice']) > 0.001) {
                return $a['minPrice'] <=> $b['minPrice'];
            }
            return $b['platformCount'] <=> $a['platformCount'];
        });

        return $groups;
    }

    /**
     * 标题归一化：去标点/空格与促销噪音词，得到用于相似度比较的归一化串
     */
    private function normTitle(string $t): string {
        $t = preg_replace('/[^\p{Han}A-Za-z0-9]/u', '', $t);
        $stop = ['包邮','券后','优惠券','旗舰店','官方','正品','同款','现货','顺丰','免运费','百亿补贴',
            '天猫','淘宝','京东','拼多多','京东自营','促销','热销','爆款','新品','包退','运费险','专柜',
            '代购','原装','正品保证','限时','秒杀','抢','拍下','立减','满减','折扣','买','送','赠','元','个'];
        $t = str_replace($stop, '', $t);
        return mb_strtolower($t, 'UTF-8');
    }

    /**
     * 标题相似度：字符级 bigram Jaccard 系数（中英文混合友好）
     */
    private function titleSim(string $a, string $b): float {
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b) {
            return 1;
        }
        $ga = $this->bigrams($a);
        $gb = $this->bigrams($b);
        if (empty($ga) || empty($gb)) {
            return 0;
        }
        $inter = count(array_intersect($ga, $gb));
        $union = count(array_unique(array_merge($ga, $gb)));
        return $union ? $inter / $union : 0;
    }

    private function bigrams(string $s): array {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $n = count($chars);
        if ($n <= 1) {
            return $chars;
        }
        $bg = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $bg[] = $chars[$i] . $chars[$i + 1];
        }
        return $bg;
    }

    private function createTjkClient() {
        $api = \app\common\ConfigStore::load('api');
        $dtkAppKey = $api['dtk_appkey'] ?? '';
        $dtkAppSecret = $api['dtk_appsecret'] ?? '';
        $hdkApiKey = $api['hdk_appkey'] ?? '';

        if (empty($dtkAppKey) && empty($hdkApiKey)) {
            return null;
        }

        // 使用完整配置：早期仅传 appkey，导致转链 pid、好单库 unionId、
        // 拼多多官方 SDK(clientId/secret/pid) 全部丢失，表现为"转链无佣金""拼多多走不了官方通道"。
        $api = \ZhiCms\ext\Tjk::loadFullApiConfig();
        return \ZhiCms\ext\Tjk::factory([
            'DtkappKey'      => $dtkAppKey,
            'DtkappSecret'   => $dtkAppSecret,
            'HdkApiKey'      => $hdkApiKey,
            'HdkUnionId'     => $api['hdk_union_id'] ?? '',
            'HdkVipPid'      => $api['hdk_vip_pid'] ?? '',
            'HdkPddPid'      => $api['hdk_pdd_pid'] ?? '',
            'PddClientId'    => $api['pdd_client_id'] ?? '',
            'PddClientSecret'=> $api['pdd_client_secret'] ?? '',
            'PddPid'         => $api['pdd_pid'] ?? ($api['hdk_pdd_pid'] ?? ''),
            'pid'            => $api['dtk_pid'] ?? ($api['tb_pid'] ?? ''),
        ]);
    }

    /**
     * 支持的平台列表（电商平臺维度）。
     * 淘宝=大淘客+好单库淘宝；拼多多/京东/唯品会=好单库对应接口。
     */
    public function getPlatforms(){

        $this->checkManageSession();

        $platforms = [
            ['key' => 'taobao', 'name' => '淘宝',  'laiyuan' => 1, 'enabled' => true],
            ['key' => 'pdd',    'name' => '拼多多', 'laiyuan' => 2, 'enabled' => true],
            ['key' => 'jd',     'name' => '京东',   'laiyuan' => 4, 'enabled' => true],
            ['key' => 'vip',    'name' => '唯品会', 'laiyuan' => 3, 'enabled' => true],
        ];
        exit(json_encode(array("info" => "获取成功", "status" => "y", "data" => $platforms)));
    }

    /**
     * 平台标识 -> 选品库 laiyuan 来源值
     */
    private function platformToLaiyuan($platform) {
        switch (strtolower($platform)) {
            case 'pdd': return 2;
            case 'vip': return 3;
            case 'jd':  return 4;
            default:    return 1; // 淘宝 / 天猫
        }
    }

    public function searchApi(){

        $this->checkManageSession();

        try {
            $keyword = $this->arg("keyword");
            $page = intval($this->arg("page", 1));
            $pageSize = intval($this->arg("pageSize", 20));
            $platform = strtolower($this->arg("platform", 'dtk'));

            if (empty($keyword)) {
                exit(json_encode(array("info" => "请输入搜索关键词", "status" => "n")));
            }

            $client = $this->createTjkClient();
            if (!$client) {
                exit(json_encode(array("info" => "请先在后台配置API参数", "status" => "n")));
            }

            $response = $client->searchGoods($keyword, $platform, $page, $pageSize);

            if ($response['code'] != 1) {
                exit(json_encode(array("info" => $response['message'], "status" => "n")));
            }

            exit(json_encode(array("info" => "搜索成功", "status" => "y", "data" => $response, "platform" => $platform)));

        } catch (\Throwable $e) {
            exit(json_encode(array("info" => "发生异常: " . $e->getMessage(), "status" => "n")));
        }
    }

    /**
     * 一键更新：拉取 GetNewestGoods（不返回 title/mainPic），仅更新选品库中已存在商品的
     * 价格 / 优惠券 / 销量 / 佣金等动态字段，不插入新商品（新商品需从联盟库手动采集）。
     */
    public function syncGoods(){

        $this->checkManageSession();
        $this->checkCsrfToken();

        try {
            $client = $this->createTjkClient();
            if (!$client) {
                exit(json_encode(array("info" => "请先在后台配置API参数", "status" => "n")));
            }

            $pageSize = 100;
            $maxPages = 6;
            $totalCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;

            for ($page = 1; $page <= $maxPages; $page++) {
                $response = $client->getNewestGoods($pageSize, $page);

                if ($response['code'] != 1 || empty($response['items'])) {
                    break;
                }

                foreach ($response['items'] as $item) {
                    // 大淘客（淘宝）入库 ID 用 goodsSign，GetNewestGoods 返回的 goodsId 是带“-”的加密串，
                    // 不能用它去查库（库里 goodsId 列存的是 goodsSign），否则永远查不到、更新被全部跳过。
                    // syncGoods 仅拉取大淘客 GetNewestGoods，入库 ID 固定用 goodsSign
                    $goodsId = $item['goodsSign'] ?? '';
                    if (empty($goodsId)) {
                        $goodsId = $item['goodsId'] ?? '';
                    }
                    if (empty($goodsId)) {
                        continue;
                    }

                    $totalCount++;

                    // 只更新已存在于选品库中的商品，不插入新商品
                    // GetNewestGoods/详情接口均不返回 title/mainPic，仅用于更新价格/优惠券/销量/佣金等
                    $existing = $this->getSelectionRow($goodsId);
                    if (!$existing) {
                        $this->writeCollectLog("跳过（选品库中不存在，不新增） goodsId={$goodsId}");
                        $skippedCount++;
                        continue;
                    }

                    $this->writeCollectLog("更新中 goodsId={$goodsId}");
                    $this->saveGoodsBatch([$item], 'newest');
                    $updatedCount++;
                }
            }

            exit(json_encode(array(
                "info" => "更新完成：处理{$totalCount}条，更新{$updatedCount}条，跳过{$skippedCount}条",
                "status" => "y",
                "total" => $updatedCount
            )));

        } catch (\Throwable $e) {
            exit(json_encode(array("info" => "发生异常: " . $e->getMessage(), "status" => "n")));
        }
    }

    /**
     * 商品采集（计划任务专用）：读取后台 API 配置，批量采集「最新商品」并入库（新增，不更新）。
     * 绕过后台 session / CSRF 校验，供 manage/task/run 定时触发。
     * @return array ['ok'=>bool,'output'=>string]
     */
    public function collectGoodsCron(){
        try {
            $client = $this->createTjkClient();
            if (!$client) {
                return array('ok' => false, 'output' => '请先在后台配置API参数（API设置-淘宝/好单库）');
            }

            $pageSize = 50;
            $maxPages = 3;      // 每次最多采 3 页（约 150 条），避免超时
            $totalCount = 0;
            $insertCount = 0;
            $skipCount = 0;
            $errors = array();

            // 采集平台：淘宝(大淘客) + 京东/拼多多/唯品会(好单库)
            $apiCfg = \app\common\ConfigStore::load('api');
            $cfgCids = !empty($apiCfg['goods_collect_cids']) ? $apiCfg['goods_collect_cids'] : array();
            $cids = array();
            if (is_array($cfgCids) && !empty($cfgCids)) {
                foreach ($cfgCids as $cid) { $cid = (int)$cid; if ($cid > 0) $cids[] = $cid; }
            }
            if (empty($cids)) {
                $cids = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15);
            }

            // 1) 淘宝（大淘客）
            $tbCids = $cids;
            $usedCids = array();
            for ($page = 1; $page <= $maxPages; $page++) {
                $avail = array_diff($tbCids, $usedCids);
                if (empty($avail)) $avail = $tbCids;
                $cid = $avail[array_rand($avail)];
                $usedCids[] = $cid;
                $response = $client->getGoodsList($pageSize, (string)$page, array('cid' => $cid), true);
                if ($response['code'] != 1 || empty($response['items'])) {
                    $cid2 = $tbCids[array_rand($tbCids)];
                    $response = $client->getGoodsList($pageSize, (string)$page, array('cid' => $cid2), true);
                    if ($response['code'] != 1 || empty($response['items'])) break;
                }
                foreach ($response['items'] as $item) {
                    // 显式标注淘宝来源：checkGoodsExists / saveGoodsBatch 均依赖 item_from
                    // 判定是否走 goodsSign 前缀匹配策略。getGoodsList 返回的商品不带 item_from，
                    // 若不标注，checkGoodsExists 会误判为非大淘客，导致 goodsSign 后半段变化后
                    // 重复入库或漏判"已存在"，故此处强制设为 tb（与 saveGoodsBatch 的 laiyuan=1 一致）。
                    $item['item_from'] = 'tb';
                    // 大淘客（淘宝）入库 ID 用 goodsSign，优先取 goodsSign
                    $goodsId = $item['goodsSign'] ?? $item['goodsId'] ?? '';
                    if (empty($goodsId)) continue;
                    $totalCount++;
                    if ($this->checkGoodsExists($item)) { $skipCount++; continue; }
                    $res = $this->saveGoodsBatch(array($item), 'collect', 1);
                    $insertCount += $res['count'];
                }
            }

            // 2) 京东/拼多多/唯品会（好单库，apiPf 与平台一致）
            $hdkPlatforms = array(
                'pdd' => array('apiPf' => 'pdd', 'keyword' => '女装'),
                'jd'  => array('apiPf' => 'jd',  'keyword' => '手机'),
                'vip' => array('apiPf' => 'vip', 'keyword' => '女装'),
            );
            foreach ($hdkPlatforms as $pf => $cfg) {
                $laiyuan = $this->platformToLaiyuan($pf);
                for ($page = 1; $page <= $maxPages; $page++) {
                    $resp = $client->searchGoods($cfg['keyword'], $cfg['apiPf'], $page, $pageSize);
                    if (!isset($resp['code']) || $resp['code'] != 1 || empty($resp['items'])) break;
                    foreach ($resp['items'] as $item) {
                        $goodsId = $item['goodsId'] ?? $item['goodsSign'] ?? '';
                        if (empty($goodsId)) continue;
                        $totalCount++;
                        if ($this->checkGoodsExists($item)) { $skipCount++; continue; }
                        $res = $this->saveGoodsBatch(array($item), 'collect', $laiyuan);
                        $insertCount += $res['count'];
                    }
                }
            }

            $msg = "商品采集完成：处理{$totalCount}条，新增{$insertCount}条，已存在跳过{$skipCount}条";
            if (!empty($errors)) $msg .= '；部分失败：' . implode('；', array_slice($errors, 0, 5));
            return array('ok' => true, 'output' => $msg);

        } catch (\Throwable $e) {
            return array('ok' => false, 'output' => '商品采集异常：' . $e->getMessage());
        }
    }

    public function getGoodsDetailApi(){

        $this->checkManageSession();

        try {
            $goodsId = $this->arg("goodsId");
            $platform = strtolower($this->arg("platform", 'dtk'));

            if (empty($goodsId)) {
                exit(json_encode(array("info" => "请输入商品ID", "status" => "n")));
            }

            $client = $this->createTjkClient();
            if (!$client) {
                exit(json_encode(array("info" => "请先在后台配置API参数", "status" => "n")));
            }

            $response = $client->getGoodsDetail($goodsId, $platform);

            if ($response['code'] != 1) {
                exit(json_encode(array("info" => $response['message'], "status" => "n")));
            }

            exit(json_encode(array("info" => "获取成功", "status" => "y", "data" => $response['data'])));

        } catch (\Throwable $e) {
            exit(json_encode(array("info" => "发生异常: " . $e->getMessage(), "status" => "n")));
        }
    }

    public function collectGoods(){

        $this->checkManageSession();
        $this->checkCsrfToken();

        try {
            $goodsId = $this->arg("goodsId");
            $platform = strtolower($this->arg("platform", 'taobao'));

            if (empty($goodsId)) {
                exit(json_encode(array("info" => "请输入商品ID", "status" => "n")));
            }

            $client = $this->createTjkClient();
            if (!$client) {
                exit(json_encode(array("info" => "请先在后台配置API参数", "status" => "n")));
            }

            // 第1步：获取商品数据（优先搜索接口，字段完整；失败回退详情接口）
            $item = null;
            $dataSource = '';

            $searchResponse = $client->searchGoods($goodsId, $platform, 1, 1);
            if ($searchResponse['code'] == 1 && !empty($searchResponse['items'])) {
                $item = $searchResponse['items'][0];
                $dataSource = 'searchGoods';
            }

            if (empty($item)) {
                $response = $client->getGoodsDetail($goodsId, $platform);
                if ($response['code'] == 1 && !empty($response['data'])) {
                    $item = $response['data'];
                    $dataSource = 'getGoodsDetail';
                } else {
                    $this->writeCollectLog("获取商品失败 goodsId={$goodsId} platform={$platform} search_resp=" . json_encode($searchResponse ?? [], JSON_UNESCAPED_UNICODE) . " detail_resp=" . json_encode($response, JSON_UNESCAPED_UNICODE));
                    exit(json_encode(array(
                        "info" => "未找到商品(ID:{$goodsId})，API搜索:" . ($searchResponse['message'] ?? '无') . "，API详情:" . ($response['message'] ?? '无'),
                        "status" => "n"
                    )));
                }
            }

            $this->writeCollectLog("获取商品成功 goodsId={$goodsId} source={$dataSource} title=" . mb_substr($item['title'] ?? '', 0, 30));

            // 若前端手动指定了分类（拼多多/京东/唯品会等无标准分类或分类与本地不一致时），
            // 以手动选择的本地分类为准，覆盖 API 返回的 cid（API cid 与本地分类体系不匹配）
            $reqCid = intval($this->arg("cid", 0));
            if ($reqCid > 0) {
                $item['cid'] = $reqCid;
                $item['tbcid'] = $reqCid;
                $this->writeCollectLog("使用手动选择的分类 cid={$reqCid} for goodsId={$goodsId}");
            }

            // 第2步：检查是否已存在
            $exists = $this->checkGoodsExists($item);
            $this->writeCollectLog("checkGoodsExists result=" . ($exists ? 'true' : 'false') . " for goodsId={$goodsId}");

            if ($exists) {
                $this->writeCollectLog("商品已存在，跳过入库 goodsId={$goodsId}");
                exit(json_encode(array("info" => "该商品已存在于选品库", "status" => "n")));
            }

            // 第3步：入库（写入本地选品库 yun_items），来源按平台区分
            // 注意：单商品采集用非 newest 动作，让新商品能直接插入
            // （newest 动作语义是"已存在则更新、不存在则跳过"，会导致新商品不入库）
            $laiyuan = $this->platformToLaiyuan($platform);
            $result = $this->saveGoodsBatch([$item], 'collect', $laiyuan);

            if ($result['count'] > 0) {
                exit(json_encode(array("info" => "采集成功", "status" => "y", "count" => $result['count'])));
            } else {
                $errorDetail = !empty($result['errors']) ? implode('; ', $result['errors']) : '未知错误';
                exit(json_encode(array("info" => "入库失败: " . $errorDetail, "status" => "n")));
            }

        } catch (\Throwable $e) {
            $this->writeCollectLog("采集异常: " . $e->getMessage() . ' trace=' . $e->getTraceAsString());
            exit(json_encode(array("info" => "发生异常: " . $e->getMessage(), "status" => "n")));
        }
    }

    /**
     * 批量入库：将联盟库（API 选品）中勾选的商品批量写入本地选品库(yun_items)
     * 前端提交选中的商品完整数据（含 title/mainPic/goodsId/goodsSign 等）与统一平台，
     * 后端逐条走 saveGoodsBatch（含去重 / 智能合并），返回成功与已存在跳过数量。
     */
    /**
     * 批量操作统一入口（范式对齐 FindController::batch）
     * action=stockin 时复用 batchStockIn：将勾选的联盟商品入库到选品库
     */
    public function batch(){
        $this->checkManageSession();
        $this->checkCsrfToken();
        $action = $this->arg("action");
        if ($action === 'stockin') {
            return $this->batchStockIn();
        }
        exit(json_encode(array("info" => "未知操作", "status" => "n")));
    }

    public function batchStockIn(){
        $this->checkManageSession();
        $this->checkCsrfToken();

        try {
            // 注意：Controller::arg() 会对字符串做 htmlspecialchars(ENT_QUOTES)，
            // 会把 JSON 的双引号转成 &quot; 导致 json_decode 失败。
            // 因此必须直接从 $_POST 取原始值并 html_entity_decode 还原后再解析。
            $raw = isset($_POST['items']) ? $_POST['items'] : $this->arg("items");
            if (is_string($raw)) {
                $raw = json_decode(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'), true);
            }
            if (!is_array($raw) || empty($raw)) {
                exit(json_encode(array("info" => "请选择要入库的商品", "status" => "n")));
            }

            $platform = strtolower($this->arg("platform", 'taobao'));
            $laiyuan = $this->platformToLaiyuan($platform);

            // 应用手动选择的本地分类（若有），以本地分类体系为准
            $reqCid = intval($this->arg("cid", 0));

            $total = 0;
            $inserted = 0;
            $skipped = 0;
            $errors = array();

            foreach ($raw as $item) {
                if (!is_array($item)) {
                    continue;
                }
                // 前端可能传 goodsId / goodsSign / title 等，统一补全 item_from
                if (empty($item['item_from'])) {
                    $item['item_from'] = ($laiyuan == 1) ? 'tb' : $platform;
                }
                if ($reqCid > 0) {
                    $item['cid'] = $reqCid;
                    $item['tbcid'] = $reqCid;
                }
                // 淘宝（大淘客）必须保证 goodsSign 存在；若仅有 goodsId 而缺 goodsSign，尝试补查详情
                $isDtk = ($laiyuan == 1) || in_array(strtolower($item['item_from'] ?? ''), array('tb','taobao','dtk'), true);
                if ($isDtk && empty($item['goodsSign'])) {
                    $client = $this->createTjkClient();
                    if ($client) {
                        $detail = $client->getGoodsDetail($item['goodsId'] ?? '', $platform);
                        if (($detail['code'] ?? 0) == 1 && !empty($detail['data'])) {
                            $item = array_merge($item, $detail['data']);
                        }
                    }
                }

                $total++;
                $res = $this->saveGoodsBatch(array($item), 'collect', $laiyuan);
                $inserted += $res['count'];
                if ($res['count'] <= 0) {
                    $skipped++;
                }
                if (!empty($res['errors'])) {
                    $errors = array_merge($errors, $res['errors']);
                }
            }

            $msg = "批量入库完成：共{$total}条，新增{$inserted}条，已存在跳过{$skipped}条";
            if (!empty($errors)) {
                $msg .= '；部分失败：' . implode('；', array_slice($errors, 0, 5));
            }
            exit(json_encode(array(
                "info" => $msg,
                "status" => $inserted > 0 ? "y" : "n",
                "inserted" => $inserted,
                "skipped" => $skipped
            )));

        } catch (\Throwable $e) {
            exit(json_encode(array("info" => "发生异常: " . $e->getMessage(), "status" => "n")));
        }
    }

    public function getPrivilegeLinkApi(){

        $this->checkManageSession();

        try {
            $goodsId = $this->arg("goodsId");
            $platform = strtolower($this->arg("platform", 'dtk'));
            $pid = $this->arg("pid", '');

            if (empty($goodsId)) {
                exit(json_encode(array("info" => "请输入商品ID", "status" => "n")));
            }

            $client = $this->createTjkClient();
            if (!$client) {
                exit(json_encode(array("info" => "请先在后台配置API参数", "status" => "n")));
            }

            // 注意签名：getPrivilegeLink($goodsId, $itemUrl, $platform, $goodsSign, $pid)
            // pid 是第 5 个参数，早期误把 $pid 填到第 2 个($itemUrl)位，导致 pid 失效且把 pid 当链接传参
            $response = $client->getPrivilegeLink($goodsId, '', $platform, '', $pid);

            if ($response['code'] != 1) {
                exit(json_encode(array("info" => $response['message'], "status" => "n")));
            }

            exit(json_encode(array("info" => "获取成功", "status" => "y", "data" => $response['data'])));

        } catch (\Throwable $e) {
            exit(json_encode(array("info" => "发生异常: " . $e->getMessage(), "status" => "n")));
        }
    }

    public function parseContentApi(){

        $this->checkManageSession();

        try {
            $content = $this->arg("content");
            $platform = strtolower($this->arg("platform", 'dtk'));

            if (empty($content)) {
                exit(json_encode(array("info" => "请输入内容", "status" => "n")));
            }

            $client = $this->createTjkClient();
            if (!$client) {
                exit(json_encode(array("info" => "请先在后台配置API参数", "status" => "n")));
            }

            $response = $client->parseContent($content, $platform);

            if ($response['code'] != 1) {
                exit(json_encode(array("info" => $response['message'], "status" => "n")));
            }

            exit(json_encode(array("info" => "解析成功", "status" => "y", "data" => $response['data'])));

        } catch (\Throwable $e) {
            exit(json_encode(array("info" => "发生异常: " . $e->getMessage(), "status" => "n")));
        }
    }

    /**
     * 批量保存商品到本地选品库(yun_items)
     * @param array  $items   商品数组（已是 API 标准化字段）
     * @param string $action  'newest' 表示来自 GetNewestGoods 的一键更新（存在则智能合并更新，否则插入）
     * @param int    $laiyuan 来源标识
     */
    private function saveGoodsBatch(array $items, string $action, int $laiyuan = 1): array {
        $count = 0;
        $errors = [];

        foreach ($items as $item) {
            // 入库商品 ID 取值策略：
            // 1) 大淘客（淘宝/天猫，laiyuan=1 或 item_from=tb）：goodsId 是带“-”的加密串，
            //    直接用它当主键会导致转链/解析失败，故统一用 goodsSign 作为入库 ID（大淘客转链/详情的稳定标识）。
            // 2) 拼多多/京东/唯品会（好单库）：goodsId 为数字，无 goodsSign 概念，保持原值。
            $itemFrom = strtolower(trim($item['item_from'] ?? ''));
            $isDtk = ($laiyuan == 1) || ($itemFrom === 'tb') || ($itemFrom === 'taobao') || ($itemFrom === 'dtk');
            if ($isDtk) {
                $goodsId = $item['goodsSign'] ?? '';
                if ($goodsId == '') {
                    $goodsId = $item['goodsId'] ?? ''; // goodsSign 缺失时回退完整 goodsId（不再截断“-”）
                }
            } else {
                $goodsId = $item['goodsId'] ?? $item['goodsSign'] ?? '';
            }

            if (empty($goodsId)) {
                $errors[] = 'goodsId为空，跳过';
                continue;
            }

            // 定位已存在商品：大淘客用 goodsSign 前缀匹配（后半段每次变化，前缀才是同一商品的稳定标识）；
            // 好单库等用完整 goodsId 精确匹配。返回整行便于后续「刷新 goodsSign / 智能更新」。
            $existsRow = $this->getSelectionRow($goodsId, $itemFrom);
            $exists = !empty($existsRow);

            // 根据API实际返回的字段进行处理，只包含yun_items表实际存在的字段
            // 统一字段入库：键名与 Tjk::standardizeItem 输出、yun_items 表字段一一对应
            // 入库兜底：item_from 统一规范（dtk/taobao/tmall/tm 全部归并为 tb），
            // 防止任何上游未规范分支把脏值写进库，导致后续转链 jump() 白名单拦截 400。
            $rawFrom = strtolower(trim((string)($item['item_from'] ?? '')));
            $normFrom = $rawFrom;
            if (in_array($rawFrom, ['tb', 'taobao', 'dtk', 'tmall', 'tm'], true)) {
                $normFrom = 'tb';
            } elseif (!in_array($rawFrom, ['jd', 'pdd', 'vip'], true)) {
                $normFrom = '';
            }
            $data = [
                'laiyuan' => $laiyuan,
                'item_from' => $normFrom,
                'goodsId' => $goodsId,
                'goodsSign' => $item['goodsSign'] ?? '',
                'title' => $item['title'] ?? '',
                'dtitle' => $item['dtitle'] ?? '',
                'content' => $item['content'] ?? '',
                'itemLink' => $item['itemLink'] ?? '',
                'mainPic' => $item['mainPic'] ?? '',
                'marketingMainPic' => $item['marketingMainPic'] ?? '',

                // 价格 / 折扣 / 券额
                'originalPrice' => floatval($item['originalPrice'] ?? 0),
                'actualPrice' => floatval($item['actualPrice'] ?? 0),
                'discounts' => floatval($item['discounts'] ?? 0),
                'couponPrice' => floatval($item['couponPrice'] ?? 0),

                // 优惠券
                'couponLink' => $item['couponLink'] ?? '',
                'couponStartTime' => $item['couponStartTime'] ?? '0',
                'couponEndTime' => $item['couponEndTime'] ?? '0',
                'couponConditions' => $item['couponConditions'] ?? '',
                'couponTotalNum' => intval($item['couponTotalNum'] ?? 0),
                'couponReceiveNum' => intval($item['couponReceiveNum'] ?? 0),
                'couponRemainCount' => intval($item['couponRemainCount'] ?? 0),
                'couponId' => $item['couponId'] ?? '',

                // 佣金
                'commissionRate' => floatval($item['commissionRate'] ?? 0),
                'commissionType' => intval($item['commissionType'] ?? 0),

                // 销量
                'monthSales' => intval($item['monthSales'] ?? 0),
                'twoHoursSales' => intval($item['twoHoursSales'] ?? 0),
                'dailySales' => intval($item['dailySales'] ?? 0),

                // 店铺
                'shopType' => intval($item['shopType'] ?? 0),
                'shopName' => $item['shopName'] ?? '',
                'shopId' => intval($item['shopId'] ?? $item['sellerId'] ?? 0),
                'shopLevel' => intval($item['shopLevel'] ?? 0),
                'shopLogo' => $item['shopLogo'] ?? '',

                // 分类 / 品牌
                'cid' => intval($item['cid'] ?? 0),
                'subcid' => is_array($item['subcid'] ?? '') ? json_encode($item['subcid'], JSON_UNESCAPED_UNICODE) : ($item['subcid'] ?? ''),
                'tbcid' => intval($item['tbcid'] ?? 0),
                'brand' => intval($item['brand'] ?? 0),
                'brandId' => intval($item['brandId'] ?? 0),
                'brandName' => $item['brandName'] ?? '',

                // 活动
                'activityType' => intval($item['activityType'] ?? 0),
                'activityStartTime' => $item['activityStartTime'] ?? '0',
                'activityEndTime' => $item['activityEndTime'] ?? '0',
                'activityName' => $item['activityName'] ?? '',
                'activityId' => intval($item['activityId'] ?? 0),

                // 其他
                'createTime' => $item['createTime'] ?? '',
                'detailPics' => is_array($item['detailPics'] ?? '') ? json_encode($item['detailPics'], JSON_UNESCAPED_UNICODE) : ($item['detailPics'] ?? ''),
                'yunfeixian' => intval($item['yunfeixian'] ?? 0),
                'freeshipRemoteDistrict' => intval($item['freeshipRemoteDistrict'] ?? 0),
                'choice' => intval($item['choice'] ?? 0),
                'hotPush' => intval($item['hotPush'] ?? 0),
                'goldSellers' => intval($item['goldSellers'] ?? 0),
                'haitao' => intval($item['haitao'] ?? 0),
                'tchaoshi' => intval($item['tchaoshi'] ?? 0),
                'estimateAmount' => floatval($item['estimateAmount'] ?? 0),
                'specialText' => $item['specialText'] ?? '',
                'inspectedGoods' => intval($item['inspectedGoods'] ?? 0),

                // 店铺评分
                'dsrScore' => floatval($item['dsrScore'] ?? 0),
                'dsrPercent' => floatval($item['dsrPercent'] ?? 0),
                'shipScore' => floatval($item['shipScore'] ?? 0),
                'shipPercent' => floatval($item['shipPercent'] ?? 0),
                'serviceScore' => floatval($item['serviceScore'] ?? 0),
                'servicePercent' => floatval($item['servicePercent'] ?? 0),
                'quanMLink' => intval($item['quanMLink'] ?? 0),
                'hzQuanOver' => intval($item['hzQuanOver'] ?? 0),

                // 系统字段
                'del' => 0,
                'top' => 0,
                'top_stime' => '0',
                'top_etime' => '0'
            ];

            // 防御：API 可能返回数组/对象字段，统一转标量，避免 bind_param 因数组值抛 TypeError 导致进程崩溃
            foreach ($data as $k => $v) {
                if (is_array($v) || is_object($v)) {
                    $data[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
                }
            }

            try {
                $this->writeCollectLog("准备入库 goodsId={$goodsId} title=" . mb_substr($item['goodsName'] ?? $item['title'] ?? '', 0, 50));

                if ($exists) {
                    // 已存在：用主键 id 定位（大淘客 goodsSign 后半段会变，绝不能再用 goodsId 精确匹配）。
                    // 智能合并：价格/券/销量等动态字段用 API 新值，title/mainPic 等保留旧值；
                    // 同时把 goodsId/goodsSign 刷新为本次采集到的最新完整 goodsSign，保证后续转链始终用最新 ID。
                    $pkId = $existsRow['id'] ?? 0;
                    if (empty($pkId)) {
                        $this->writeCollectLog("已存在但无主键 id，跳过 goodsId={$goodsId}");
                        continue;
                    }
                    $updateData = $this->smartUpdateMerge($data, $existsRow);
                    // 显式刷新 goodsSign 相关主键字段（确保用最新完整 goodsSign）
                    $updateData['goodsId'] = $data['goodsId'];
                    $updateData['goodsSign'] = $data['goodsSign'];
                    foreach ($updateData as $k => $v) {
                        if (is_array($v) || is_object($v)) {
                            $updateData[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
                        }
                    }
                    $this->writeCollectLog("更新商品 id={$pkId} goodsId={$goodsId}");
                    obj("api/ApiData")->dataUpdate("yun_items", $updateData, array('id' => intval($pkId)));
                } else {
                    // 不存在：插入新商品
                    if ($action === 'newest') {
                        // 一键更新模式（GetNewestGoods 不返回 title/mainPic）不新增，仅跳过
                        $this->writeCollectLog("跳过入库（选品库不存在，不新增） goodsId={$goodsId}");
                        continue;
                    }
                    $this->writeCollectLog("插入新商品 goodsId={$goodsId}");
                    $insertId = obj("api/ApiData")->insertData("yun_items", $data);
                    $this->writeCollectLog("插入成功，ID={$insertId}");
                }
                $count++;
            } catch (\Throwable $e) {
                $errMsg = 'goodsId=' . $goodsId . ' error=' . $e->getMessage() . ' trace=' . $e->getTraceAsString();
                $errors[] = $errMsg;
                $this->writeCollectLog($errMsg);
            }
        }

        return ['count' => $count, 'errors' => $errors];
    }

    /**
     * 写入采集日志到 data/log/collect.log
     */
    private function writeCollectLog($msg) {
        $logFile = defined('\ROOT_PATH') ? \ROOT_PATH . 'data/log/collect.log' : __DIR__ . '/../../data/log/collect.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $time = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$time}] {$msg}\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * 智能更新合并：只更新API返回的有效字段，保留数据库中原有的正确数据
     */
    private function smartUpdateMerge(array $newData, array $oldData): array {
        $mergedData = [];

        // 只处理数据库中实际存在的字段
        $existingFields = array_keys($oldData);

        foreach ($existingFields as $field) {
            if (array_key_exists($field, $newData)) {
                $newValue = $newData[$field];
                $oldValue = $oldData[$field];

                // 根据不同字段类型判断是否为有效值
                $isValidNewValue = $this->isValidFieldValue($field, $newValue);

                if ($isValidNewValue) {
                    // API返回有效值，使用新值
                    $mergedData[$field] = $newValue;
                } else {
                    // API返回空值或无效值，保留原有值
                    $mergedData[$field] = $oldValue;
                }
            } else {
                // 新数据中没有此字段，保留原有值
                $mergedData[$field] = $oldData[$field];
            }
        }

        return $mergedData;
    }

    /**
     * 判断字段值是否为有效值（非空、非默认值）
     */
    private function isValidFieldValue(string $field, $value): bool {
        // 基本空值检查
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            // 对于特定字段，0可能是有效值
            if (in_array($field, ['view', 'like', 'lock', 'choice', 'freeshipRemoteDistrict', 'yunfeixian', 'del', 'top', 'commissionType'])) {
                return true; // 这些字段的0是有效值
            }

            // 时间字段的特殊处理
            if (in_array($field, ['couponEndTime', 'couponStartTime'])) {
                return $value !== '0' && $value !== '';
            }

            return false;
        }

        // 字符串类型字段的进一步检查
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * 提取大淘客 goodsSign 的稳定前缀（同一淘宝客账号+同一商品的不变部分）。
     * 大淘客 goodsSign 为「两段式」：以单个“-”分隔，前半段是账号+商品的稳定标识，
     * 后半段每次采集/转链都会变化（仅后半段变、前半段不变）。
     * 因此去重/定位同一商品必须以「前缀」为准，不能用完整 goodsSign 做唯一匹配，
     * 否则后半段变化会导致查不到、重复入库或更新失效。无“-”时回退完整串。
     */
    private function goodsSignPrefix($goodsSign) {
        if (empty($goodsSign) || !is_string($goodsSign)) {
            return '';
        }
        $pos = strpos($goodsSign, '-');
        return $pos === false ? $goodsSign : substr($goodsSign, 0, $pos);
    }

    /**
     * 查询选品库中是否已存在该商品（返回整行，便于判断标题是否完整）。
     * 大淘客（淘宝）入库 ID 实为 goodsSign，且 goodsSign 后半段每次变化，
     * 故用 goodsSign 前缀匹配定位同一商品，而非完整 goodsSign。
     */
    private function getSelectionRow($goodsId, $itemFrom = '') {
        if (empty($goodsId)) {
            return null;
        }
        $itemFrom = strtolower(trim($itemFrom));
        $isDtk = ($itemFrom === 'tb') || ($itemFrom === 'taobao') || ($itemFrom === 'dtk');
        if ($isDtk) {
            $prefix = $this->goodsSignPrefix($goodsId);
            if ($prefix === '') {
                return null;
            }
            // goodsId 列存的是完整 goodsSign，LIKE 'prefix-%' 即可命中同一商品
            $sql = "SELECT * FROM `{pre}items` WHERE `goodsId` LIKE ?";
            $rows = obj('api/ApiData')->thisQuery($sql, [$prefix . '-%']);
            return (!empty($rows)) ? $rows[0] : null;
        }
        $where['goodsId'] = $goodsId;
        $rows = obj("api/ApiData")->dataSelect("yun_items", $where);
        return $rows ? $rows[0] : null;
    }

    private function checkGoodsExists($item) {
        // 大淘客（淘宝）入库 ID 实为 goodsSign，且 goodsSign 后半段每次变化，
        // 故判断存在性必须以 goodsSign 前缀为准（而非完整 goodsSign），否则后半段变化会判为不存在。
        $itemFrom = strtolower(trim($item['item_from'] ?? ''));
        $isDtk = ($itemFrom === 'tb') || ($itemFrom === 'taobao') || ($itemFrom === 'dtk');
        if ($isDtk) {
            $goodsId = $item['goodsSign'] ?? ($item['goodsId'] ?? '');
        } else {
            $goodsId = $item['goodsId'] ?? ($item['goodsSign'] ?? '');
        }
        $shopName = $item['shopName'] ?? '';
        $shopId = $item['shopId'] ?? $item['sellerId'] ?? 0;

        if (empty($goodsId) && empty($shopName) && empty($shopId)) {
            return false;
        }

        $whereParts = [];
        $params = [];

        if ($isDtk && !empty($goodsId)) {
            // 前缀匹配：goodsId 列存完整 goodsSign，LIKE 'prefix-%' 命中同一商品
            $prefix = $this->goodsSignPrefix($goodsId);
            if ($prefix !== '') {
                $whereParts[] = "`goodsId` LIKE ?";
                $params[] = $prefix . '-%';
            }
        } elseif (!empty($goodsId)) {
            $whereParts[] = "`goodsId` = ?";
            $params[] = $goodsId;
        }
        if (!empty($shopName)) {
            $whereParts[] = "`shopName` = ?";
            $params[] = $shopName;
        }
        if (!empty($shopId)) {
            $whereParts[] = "`shopId` = ?";
            $params[] = $shopId;
        }

        if (!empty($whereParts)) {
            $whereStr = implode(' OR ', $whereParts);
            $sql = "SELECT COUNT(*) as count FROM `{pre}items` WHERE {$whereStr}";
            $result = obj('api/ApiData')->thisQuery($sql, $params);
            if ($result && isset($result[0]['count']) && $result[0]['count'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 拼多多请求共用方法（多多进宝官方接口）
     * @param string $type   接口名 如 pdd.ddk.rp.prom.url.generate
     * @param array  $params 业务参数（不含公共参数）
     * @return array [code, data, pid]
     */
    /**
     * 读取拼多多凭证配置（合并 DB 与 apiset.php 文件，DB 优先）
     * @return array ['pdd_client_id'=>..., 'pdd_client_secret'=>..., 'pdd_pid'=>...]
     */
    private function pddConfig()
    {
        $api = array();
        $file = \CONFIG_PATH . 'apiset.php';
        if (is_file($file)) {
            include $file;                 // 文件内定义 $api
            if (isset($api) && is_array($api)) {
                $api = $api;
            }
        }
        $dbApi = \app\common\ConfigStore::load('api');
        if (is_array($dbApi) && !empty($dbApi)) {
            $api = array_merge($api, $dbApi); // DB 覆盖文件
        }
        return $api;
    }

    private function pddRequest($type, $params = [])
    {
        $api = $this->pddConfig();
        $clientId = $api['pdd_client_id'] ?? '';
        $clientSecret = $api['pdd_client_secret'] ?? '';
        $pid = $api['pdd_pid'] ?? '';
        if (!$clientId || !$clientSecret) {
            return ['fail', '未配置拼多多 client_id / client_secret，请在系统设置-接口配置中填写', $pid];
        }
        $params['type'] = $type;
        $params['client_id'] = $clientId;
        $params['timestamp'] = strval(time() * 1000);
        $params['data_type'] = 'JSON';
        ksort($params);
        $signStr = $clientSecret;
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $signStr .= $k . $v;
        }
        $signStr .= $clientSecret;
        $params['sign'] = strtoupper(md5($signStr));
        $ch = curl_init('https://gw-api.pinduoduo.com/api/router');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
        ]);
        $out = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($out === false) {
            return ['fail', 'curl 请求失败：' . $err, $pid];
        }
        $json = json_decode($out, true);
        if (!is_array($json)) {
            return ['fail', '接口返回非 JSON：' . mb_substr($out, 0, 200), $pid];
        }
        return ['success', $json, $pid];
    }

    /**
     * 生成拼多多推广位备案授权链接（pdd.ddk.rp.prom.url.generate）
     * 前端弹窗展示链接，用户打开后在拼多多完成授权备案
     */
    public function pddAuthUrl()
    {
        $this->checkCsrfToken();
        $api = $this->pddConfig();
        $pid = $api['pdd_pid'] ?? '';
        list($code, $data) = $this->pddRequest('pdd.ddk.rp.prom.url.generate', [
            'p_id_list'    => json_encode([$pid]),
            'channel_type' => 10,
        ]);
        if ($code === 'fail') {
            exit(json_encode(['status' => 'n', 'info' => $data]));
        }
        $url = '';
        $urlList = $data['rp_promotion_url_generate_response']['url_list'] ?? [];
        if (!empty($urlList) && is_array($urlList)) {
            $first = $urlList[0];
            $url = is_array($first) ? ($first['url'] ?? '') : $first;
        }
        if (!$url && isset($data['url_list'])) {
            $ul = $data['url_list'];
            $url = is_array($ul) ? ($ul[0]['url'] ?? ($ul[0] ?? '')) : $ul;
        }
        if (!$url) {
            exit(json_encode(['status' => 'n', 'info' => '未获取到授权链接：' . json_encode($data, JSON_UNESCAPED_UNICODE)]));
        }
        exit(json_encode(['status' => 'y', 'info' => '获取成功，请在打开的页面完成拼多多授权备案', 'url' => $url, 'pid' => $pid]));
    }

    /**
     * 查询当前拼多多推广位是否已绑定备案（pdd.ddk.member.authority.query）
     * bind=1 已绑定，bind=0 未绑定
     */
    public function pddAuthStatus()
    {
        $this->checkCsrfToken();
        $api = $this->pddConfig();
        $pid = $api['pdd_pid'] ?? '';
        list($code, $data) = $this->pddRequest('pdd.ddk.member.authority.query', [
            'pid' => $pid,
        ]);
        if ($code === 'fail') {
            exit(json_encode(['status' => 'n', 'info' => $data]));
        }
        $resp = $data['authority_query_response'] ?? [];
        $bind = isset($resp['bind']) ? intval($resp['bind']) : -1;
        $msg = $bind === 1 ? '已绑定备案（可正常调用商品搜索接口）'
            : ($bind === 0 ? '未绑定备案（调用商品搜索会报 60001 错误）' : '查询状态未知');
        exit(json_encode([
            'status'   => 'y',
            'info'     => $msg,
            'bind'     => $bind,
            'pid'      => $pid,
            'response' => $resp,
        ]));
    }

    /**
     * 用 client_id / client_secret 自动生成拼多多推广位 PID（pdd.goods.pid.generate）
     * 生成成功后写回 yun_apis 的 pdd_pid
     */
    public function pddGeneratePid()
    {
        $this->checkCsrfToken();
        $api = $this->pddConfig();
        $clientId = $api['pdd_client_id'] ?? '';
        $clientSecret = $api['pdd_client_secret'] ?? '';
        if (!$clientId || !$clientSecret) {
            exit(json_encode(['status' => 'n', 'info' => '请先在联盟设置中填写拼多多 client_id / client_secret 并保存']));
        }
        list($code, $data) = $this->pddRequest('pdd.goods.pid.generate', [
            'number'         => 1,
            'p_id_name_list' => json_encode(['zhicms']),
        ]);
        if ($code === 'fail') {
            exit(json_encode(['status' => 'n', 'info' => $data]));
        }
        $resp = $data['p_id_generate_response'] ?? [];
        $pidList = $resp['p_id_list'] ?? [];
        $pid = '';
        if (!empty($pidList) && is_array($pidList)) {
            $first = $pidList[0];
            $pid = is_array($first) ? ($first['p_id'] ?? '') : $first;
        }
        if (!$pid) {
            exit(json_encode(['status' => 'n', 'info' => '生成失败：' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]));
        }
        $api['pdd_pid'] = $pid;
        \app\common\ConfigStore::save('api', $api);
        \app\common\ConfigStore::clearCache('api');
        exit(json_encode(['status' => 'y', 'info' => 'PID 生成成功', 'pid' => $pid]));
    }

    /**
     * 拼多多备案成功后，自动把自写 SDK 授权加入联盟授权列表（yun_union_auth）
     * 前置：pdd.ddk.member.authority.query 查询 bind=1 才允许加入
     */
    public function pddAuthAddToList()
    {
        $this->checkCsrfToken();
        $api = $this->pddConfig();
        $pid = $api['pdd_pid'] ?? '';
        if (!$pid) {
            exit(json_encode(['status' => 'n', 'info' => '尚未生成 PID，请先点击「生成 PID」']));
        }
        // 确认已备案
        list($code, $data) = $this->pddRequest('pdd.ddk.member.authority.query', ['pid' => $pid]);
        if ($code === 'fail') {
            exit(json_encode(['status' => 'n', 'info' => $data]));
        }
        $resp = $data['authority_query_response'] ?? [];
        $bind = isset($resp['bind']) ? intval($resp['bind']) : 0;
        if ($bind !== 1) {
            exit(json_encode(['status' => 'n', 'info' => '该 PID 尚未在拼多多完成备案，无法加入列表']));
        }
        $exists = obj("api/ApiData")->thisQuery("SELECT * FROM `{pre}union_auth` WHERE `platform`='pdd' AND `union_type`='pdd' LIMIT 1");
        $row = is_array($exists) && !empty($exists) ? $exists[0] : null;
        $rec = array(
            'platform'   => 'pdd',
            'name'       => '拼多多自写SDK',
            'pid'        => $pid,
            'union_type' => 'pdd',
            'auth_type'  => 'pdd_sdk',
            'beian'      => 1,
            'add_time'   => time(),
        );
        if ($row) {
            obj("api/ApiData")->dataUpdate("{pre}union_auth", $rec, "`id`=?", array($row['id']));
        } else {
            obj("api/ApiData")->insertData("{pre}union_auth", $rec);
        }
        exit(json_encode(['status' => 'y', 'info' => '已添加至授权列表，PID 备案成功']));
    }
}
