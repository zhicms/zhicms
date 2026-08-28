<?php
namespace app\common;

/**
 * 公共数据服务
 * 提取自 app\index\controller\GlobalController 的数据查询逻辑
 * 用于模板和控制器中获取分类、用户、商城等公共数据
 */
class DataService {

    public function type($lock, $id, $table) {
        if ($lock != "y") return null;
        if ($id != 'null') {
            $id = (int)$id;
            $where[] = " `id` ={$id}";
            return obj("api/ApiData")->dataSelect($table, $where);
        }
        $where[] = "1";
        return obj("api/ApiData")->dataSelect($table, $where, "`id` ASC");
    }

    public function mallType($lock, $id, $table, $type) {
        if ($lock != "y") return null;
        $id = (int)$id;
        $where[] = " `mallType` ={$id}";
        if ($type != 'null') {
            return obj("api/ApiData")->dataSelect($table, $where);
        }
        return obj("api/ApiData")->dataSelect($table, $where, "`id` ASC");
    }

    public function yqfType($lock, $id, $table, $type) {
        if ($lock != "y") return null;
        $id = (int)$id;
        $where[] = " `mallType` ={$id}";
        if ($type != 'null') {
            return obj("api/ApiData")->dataSelect($table, $where);
        }
        return obj("api/ApiData")->dataSelect($table, $where, "`id` ASC");
    }

    public function yqfMallType($lock, $id, $table, $type) {
        if ($lock != "y") return null;
        $id = (int)$id;
        $where[] = " `id` ={$id}";
        if ($type != 'null') {
            return obj("api/ApiData")->dataSelect($table, $where);
        }
        return obj("api/ApiData")->dataSelect($table, $where, "`id` ASC");
    }

    public function duoMaiType($lock, $id, $table, $type) {
        if ($lock != "y") return null;
        $safeId = addslashes($id);
        if ($type != 'null') {
            $where[] = " `country` !='" . $safeId . "'";
            return obj("api/ApiData")->dataSelect($table, $where, " RAND() DESC LIMIT 20");
        }
        $where[] = "`country` ='" . $safeId . "'";
        return obj("api/ApiData")->dataSelect($table, $where, " RAND() DESC LIMIT 20");
    }

    public function union($lock, $mallId) {
        if ($lock != "y") return null;
        $mallId = (int)$mallId;
        $where[] = "  `id` ={$mallId} ";
        $retMall = obj("api/ApiData")->dataSelect("yun_mall", $where);
        if (empty($retMall) || empty($retMall['union'])) {
            exit('该商城未指定联盟类型!');
        }
        $whereUnion[] = "`id` =" . (int)$retMall['union'];
        $retUnion = obj("api/ApiData")->dataSelect("yun_union", $whereUnion);
        return $retUnion["type"];
    }

    public function loadMall($lock, $type) {
        if ($lock != "y") return null;
        $type = (int)$type;
        // 多表 JOIN：直接拼 SQL（{pre} 会被 realTable 替换为真实前缀，且 where 条件里不再硬编码表名）
        $ret = obj("api/ApiData")->thisQuery(
            "SELECT `{pre}home_mall`.* FROM `{pre}home_mall` "
            . "INNER JOIN `{pre}union` ON `{pre}home_mall`.union = `{pre}union`.id "
            . "WHERE `{pre}home_mall`.view = {$type} ORDER BY `{pre}home_mall`.px ASC"
        );

        $newData = array();
        foreach ($ret as $key => $value) {
            $newLink = str_replace(array("[TOLINK]"), array($value['link']), base64_decode($value['code']));
            $data['link'] = $value['link'];
            $data['pic']  = $value['pic'];
            $data['code'] = $newLink;
            $newData[] = $data;
        }
        return $newData;
    }

    public function aType($lock, $id) {
        if ($lock != "y") return null;
        if ($id != 'null') {
            $id = (int)$id;
            $where[] = " `id` ={$id}";
            return obj("api/ApiData")->dataSelect("yun_nav", $where);
        }
        $where[] = " 1";
        return obj("api/ApiData")->dataSelect("yun_nav", $where, "`id` DESC ");
    }

    public function vestList($lock) {
        if ($lock != "y") return null;
        $where[] = "`vest` =1";
        return obj("api/ApiData")->dataSelect("yun_user", $where, "`id` DESC");
    }

    public function findUser($lock, $uid, $model = 'null') {
        if ($lock != "y") return null;
        if ($model == 'cookie') {
            // 安全：关联数组精确匹配手机号，think-ORM 自动参数化绑定，杜绝 Cookie 注入
            $where['mobile'] = $uid;
        } else {
            $uid = (int)$uid;
            $where[] = " `id` ={$uid}";
        }
        return obj("api/ApiData")->dataSelect("yun_user", $where);
    }

    public function getList($table, $lock, $pid) {
        if ($lock != "y") return null;
        $pid = (int)$pid;
        $where[] = "`pid` ={$pid}";
        return obj("api/ApiData")->dataSelect($table, $where, "`id` ASC  ");
    }

    public function getGlList($table, $lock) {
        if ($lock != "y") return null;
        $where[] = "1";
        return obj("api/ApiData")->dataSelect($table, $where, "`id` ASC  ");
    }

    /**
     * 根据分类ID返回中文分类名（静态映射，0 次 DB 查询）
     * 注意：模板中调用为 Cid() 大写开头
     */
    public function Cid($cid) {
        return \app\base\controller\BaseController::getCategoryName($cid);
    }

    /**
     * 日历挂件（移植自 emlog Calendar）
     * 
     * 大数据量优化：
     *   原查询 SELECT ... WHERE date <= NOW() 是全表扫描
     *   改为只查询当前可见月份范围的日期，结果缓存 30 分钟
     */
    public function calendar() {
        $ym = isset($_REQUEST['ym']) ? $_REQUEST['ym'] : '';
        if (preg_match('/^\d{6}$/', $ym)) {
            $year  = (int)substr($ym, 0, 4);
            $month = (int)substr($ym, 4, 2);
        } else {
            $year  = (int)date('Y');
            $month = (int)date('m');
        }
        $year  = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        // 大数据量优化：限定日期范围查询 + 结果缓存 30 分钟
        $cache = \app\common\CacheService::instance();
        $logdate = $cache->remember('calendar_dates_' . $year . $month, function () use ($year, $month) {
            $firstDay = sprintf('%04d-%02d-01', $year, $month);
            $lastDay  = date('Y-m-d', strtotime($firstDay . ' +1 month -1 day'));
            // 扩展范围以覆盖日历前后空白格
            $fromDate = date('Y-m-d', strtotime($firstDay . ' -7 day'));
            $toDate   = date('Y-m-d', strtotime($lastDay . ' +7 day'));
            $rows = obj("api/ApiData")->thisQuery(
                "SELECT DATE_FORMAT(`date`,'%Y%m%d') AS d FROM `{pre}article` WHERE `date` >= ? AND `date` <= ?",
                [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']
            );
            $dates = [];
            if ($rows) {
                foreach ($rows as $r) { $dates[] = $r['d']; }
            }
            return $dates;
        }, 1800);

        $today  = (int)date('Ymd');
        $ymStr  = sprintf('%04d%02d', $year, $month);

        $prevMonth = $month - 1; $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1; $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

        $base = url($route = 'index/index/index', $params = array());

        $html  = '<table class="calendartop" cellspacing="0"><tr>';
        $html .= '<td colspan="2"><span class="cal-year">' . $year . ' 年 </span><span class="cal-month">' . $month . ' 月</span></td>';
        $html .= '</tr></table>';
        $html .= '<table class="calendar" cellspacing="0">';
        $html .= '<tr><td class="week">一</td><td class="week">二</td><td class="week">三</td><td class="week">四</td><td class="week">五</td><td class="week">六</td><td class="sun">日</td></tr>';

        $week = (int)date('w', mktime(0, 0, 0, $month, 1, $year));
        if ($week == 0) { $week = 7; }
        $lastday  = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $lastweek = (int)date('w', mktime(0, 0, 0, $month, $lastday, $year));

        $j = 1; $w = 7; $isend = false;
        for ($i = 1; $i <= 6; $i++) {
            if ($isend || ($i == 6 && $lastweek == 0)) { break; }
            $html .= '<tr>';
            for ($j; $j <= $w; $j++) {
                if ($j < $week) {
                    $html .= '<td>&nbsp;</td>';
                } elseif ($j <= 7) {
                    $r = $j - $week + 1;
                    $n_time = sprintf('%04d%02d%02d', $year, $month, $r);
                    $html .= $this->calCell($r, $n_time, $logdate, $today, $ymStr, $base);
                } else {
                    $t = $j - ($week - 1);
                    if ($t > $lastday) {
                        $isend = true;
                        $html .= '<td>&nbsp;</td>';
                    } else {
                        $n_time = sprintf('%04d%02d%02d', $year, $month, $t);
                        $html .= $this->calCell($t, $n_time, $logdate, $today, $ymStr, $base);
                    }
                }
            }
            $html .= '</tr>';
            $w += 7;
        }
        $html .= '</table>';
        return $html;
    }

    private function calCell($day, $n_time, $logdate, $today, $ymStr, $base) {
        if (in_array($n_time, $logdate) && $n_time == $today) {
            return '<td class="day"><em>' . $day . '</em></td>';
        } elseif (in_array($n_time, $logdate)) {
            return '<td class="day2"><em>' . $day . '</em></td>';
        } elseif ($n_time == $today) {
            return '<td class="day"><em>' . $day . '</em></td>';
        }
        return '<td>' . $day . '</td>';
    }
}
