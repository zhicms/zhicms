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
                    }
                }

                usort($items, function($a, $b) {
                    return ($b['monthSales'] ?? 0) - ($a['monthSales'] ?? 0);
                });

                $items = array_slice($items, 0, $pageSize);
            } else {
                // 淘宝：使用 get-goods-list 全量商品列表（返回完整字段含标题/主图），替代定时拉取
                if ($platform !== 'taobao') {
                    $items = [];
                    $total = 0;
                } else {
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

                    $response = $client->getGoodsList($pageSize, strval($page), $extra);

                    if ($response['code'] == 1 && !empty($response['items'])) {
                        $items = $response['items'];
                        $total = $response['total'] ?? 0;
                    } else {
                        // 将API错误信息传到视图，便于排查
                        $this->apiError = $response['message'] ?? 'API未返回数据';
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

        if (!IS_POST) {
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

        if (!IS_POST) {
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

        if (!IS_POST) {
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

        if (!IS_POST) {
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

        if(!IS_POST){
            $this->pageText = array("分类管理", "新建分类");
            $this->display('app/manage/view/union/addtype');
            exit;
        }else{
            self::checkTypeForm();
            $data = obj('api/Api')->Form($this->POSTarg());
            obj('api/ApiData')->insertData('yun_group', $data);
            echo json_encode(array("info" => "保存成功", "status" => "y"));

        }
    }

    public function editorType(){

        $this->checkManageSession();

        if(!IS_POST){
            $this->pageText = array("分类管理", "编辑分类");
            $id = intval($this->arg("id"));
            $where['id'] = $id;
            $ret = obj("api/ApiData")->dataSelect("yun_group",$where);
            $this->ret = $ret;
            $this->html = '<input type="hidden" name="id" value="'.$ret['id'].'" />';
            $this->display('app/manage/view/union/addtype');
            exit;
        }else{
            self::checkTypeForm();
            $id = intval($this->arg("id"));
            $where['id'] = $id;
            $data = obj('api/Api')->Form($this->POSTarg());
            obj("api/ApiData")->dataUpdate("yun_group",$data,$where);
            echo json_encode(array("info" => "保存成功", "status" => "y"));
        }

    }

    public function deleteType(){

        $this->checkManageSession();

        error_reporting(0);
        $id = intval($this->arg("id"));
        $where['groupid'] = $id;
        //查询该分类下面有没有文章
        $count = obj("api/ApiData")->dataCount("yun_forum", $where);
        if($count>0){
            exit(json_encode(array("info" => "请先删除改分类下的文章", "status" => "n")));
        }

        //删除
        $where = "`id` = ?";
        obj('api/ApiData')->deleteThis('yun_group', $where, array($id));
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

        if(!$this->arg("groupname")){
            exit(json_encode(array("info" => "请填写分类名称", "status" => "n")));
        }
        if(!$this->arg("px")){
            exit(json_encode(array("info" => "请填写排序", "status" => "n")));
        }
    }

    // ===================== 多麦（duomai）联盟商品同步 =====================

    public function duoMai(){

        $this->checkManageSession();

        include CONFIG_PATH . 'apiset.php';
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
        include CONFIG_PATH . 'apiset.php';
        $dtkAppKey = $api['dtk_appkey'] ?? '';
        $dtkAppSecret = $api['dtk_appsecret'] ?? '';
        $hdkApiKey = $api['hdk_appkey'] ?? '';

        if (empty($dtkAppKey) && empty($hdkApiKey)) {
            return null;
        }

        return new \ZhiCms\ext\Tjk([
            'DtkappKey' => $dtkAppKey,
            'DtkappSecret' => $dtkAppSecret,
            'HdkApiKey' => $hdkApiKey,
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
                    $goodsId = $item['goodsId'] ?? '';
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
            $laiyuan = $this->platformToLaiyuan($platform);
            $result = $this->saveGoodsBatch([$item], 'newest', $laiyuan);

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

            $response = $client->getPrivilegeLink($goodsId, $pid, $platform);

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
            // 修复：正确的字段映射（API 返回字段 -> 数据库字段）
            $goodsId = $item['goodsId'] ?? $item['goodsSign'] ?? '';
            if ($goodsId != '' && strpos($goodsId, '-') > 0) {
                $xinIidArr = explode('-', $goodsId);
                $jieIid = $xinIidArr[1];
                if ($jieIid != '') {
                    $goodsId = $jieIid;
                }
            }

            if (empty($goodsId)) {
                $errors[] = 'goodsId为空，跳过';
                continue;
            }

            $exists = $this->checkGoodsExists($item);
            if ($exists && $action !== 'newest') {
                continue;
            }

            // 根据API实际返回的字段进行处理，只包含yun_items表实际存在的字段
            // 统一字段入库：键名与 Tjk::standardizeItem 输出、yun_items 表字段一一对应
            $data = [
                'laiyuan' => $laiyuan,
                'item_from' => $item['item_from'] ?? '',
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

                if ($action === 'newest') {
                    $where = [];
                    $where['goodsId'] = $goodsId;
                    $where['laiyuan'] = intval($laiyuan);
                    $existsInDb = obj("api/ApiData")->dataSelect("yun_items", $where);

                    if ($existsInDb) {
                        // 智能更新：只更新API返回的有效字段，保留原有 title/mainPic 等数据
                        // dataSelect 走 find() 返回单行（关联数组），也可能返回含首行的列表，兼容两种结构
                        $existsRow = (isset($existsInDb[0]) && is_array($existsInDb[0])) ? $existsInDb[0] : $existsInDb;
                        $updateData = $this->smartUpdateMerge($data, $existsRow);
                        foreach ($updateData as $k => $v) {
                            if (is_array($v) || is_object($v)) {
                                $updateData[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
                            }
                        }
                        $this->writeCollectLog("智能更新商品 goodsId={$goodsId}");
                        obj("api/ApiData")->dataUpdate("yun_items", $updateData, $where);
                    } else {
                        // 一键更新模式下不插入新商品（GetNewestGoods 不返回 title/mainPic）
                        $this->writeCollectLog("跳过入库（选品库不存在，不新增） goodsId={$goodsId}");
                        continue;
                    }
                } else {
                    $this->writeCollectLog("插入新商品 goodsId={$goodsId} (非newest)");
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
        $logFile = defined('ROOT_PATH') ? ROOT_PATH . 'data/log/collect.log' : __DIR__ . '/../../data/log/collect.log';
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
     * 查询选品库中是否已存在该商品（返回整行，便于判断标题是否完整）
     */
    private function getSelectionRow($goodsId) {
        if (empty($goodsId)) {
            return null;
        }
        $where['goodsId'] = $goodsId;
        $rows = obj("api/ApiData")->dataSelect("yun_items", $where);
        return $rows ? $rows[0] : null;
    }

    private function checkGoodsExists($item) {
        $goodsId = $item['goodsId'] ?? '';
        $shopName = $item['shopName'] ?? '';
        $shopId = $item['shopId'] ?? $item['sellerId'] ?? 0;

        if (empty($goodsId) && empty($shopName) && empty($shopId)) {
            return false;
        }

        $conditions = [];
        $params = [];

        if (!empty($goodsId)) {
            $conditions[] = "`goodsId` = ?";
            $params[] = $goodsId;
        }
        if (!empty($shopName)) {
            $conditions[] = "`shopName` = ?";
            $params[] = $shopName;
        }
        if (!empty($shopId)) {
            $conditions[] = "`shopId` = ?";
            $params[] = $shopId;
        }

        $whereParts = [];
        if (count($conditions) >= 2) {
            $whereParts[] = '(' . implode(' AND ', $conditions) . ')';
        }
        if (!empty($goodsId)) {
            $whereParts[] = "`goodsId` = ?";
            $params[] = $goodsId;
        }
        if (!empty($shopName) && !empty($shopId)) {
            $whereParts[] = "`shopName` = ? AND `shopId` = ?";
            $params[] = $shopName;
            $params[] = $shopId;
        }
        if (!empty($goodsId) && !empty($shopName)) {
            $whereParts[] = "`goodsId` = ? AND `shopName` = ?";
            $params[] = $goodsId;
            $params[] = $shopName;
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
}
