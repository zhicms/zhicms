<?php
namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;

class WordController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    private $defaults = array(
        'enable_api'   => '0',
        'api_url'      => '',
        'api_key'      => '',
        'extra_words'  => '',
        'auto_check'   => '0',   // 内容发布时自动检测
    );

    /** 可扫描的内容范围 */
    private $scopes = array(
        'yun_article' => '发现/资讯',
        'yun_forum'   => '社区',
        'yun_items'   => '电商宝库',
        'yun_page'    => '单页',
    );

    private function cfg(){
        $cfg = \app\common\ConfigStore::load('word');
        if (!is_array($cfg) || empty($cfg)) $cfg = array();
        return array_merge($this->defaults, $cfg);
    }

    public function index(){
        $this->checkManageSession();
        $this->pageText = array('违规词检测');
        $this->toolTitle = '违规词检测';

        $this->cfg = $this->cfg();
        $this->wordCount = \ZhiCms\ext\WordCheck::countWords();
        $words = \ZhiCms\ext\WordCheck::listWords(300);
        // 预计算等级徽标，避免在模板中使用 {php} 赋值
        $levels = array(1 => '低', 2 => '中', 3 => '高');
        $levelClass = array(1 => 'badge-light border', 2 => 'badge-warning', 3 => 'badge-danger');
        foreach ($words as &$w) {
            $lv = (int)$w['level'];
            $w['level_label'] = isset($levels[$lv]) ? $levels[$lv] : '低';
            $w['level_badge'] = isset($levelClass[$lv]) ? $levelClass[$lv] : 'badge-light border';
        }
        unset($w);
        $this->words = $words;
        $this->scopes = $this->scopes;
        $this->display();
    }

    public function save(){
        $this->checkManageSession();
        $data = array(
            'enable_api'  => $this->arg('enable_api') ? '1' : '0',
            'api_url'     => trim($this->arg('api_url')),
            'api_key'     => trim($this->arg('api_key')),
            'extra_words' => trim($this->arg('extra_words')),
            'auto_check'  => $this->arg('auto_check') ? '1' : '0',
        );
        \app\common\ConfigStore::save('word', $data);
        \app\common\ConfigStore::clearCache('word');
        \ZhiCms\ext\AdminLog::write('word', '保存了违规词检测配置');
        $this->alert('保存成功', 'index.php?r=manage/word/index');
    }

    public function addWord(){
        $this->checkManageSession();
        $word = trim($this->arg('word'));
        $level = max(1, min(3, (int)$this->arg('level', 1)));
        $category = trim($this->arg('category'));
        if ($word === '') exit(json_encode(array('info' => '请输入违规词', 'status' => 'n')));
        $ok = \ZhiCms\ext\WordCheck::addWord($word, $level, $category);
        \ZhiCms\ext\AdminLog::write('word', '添加了违规词：' . $word);
        exit(json_encode(array('info' => $ok ? '已添加' : '添加失败', 'status' => $ok ? 'y' : 'n')));
    }

    public function delWord(){
        $this->checkManageSession();
        $id = (int)$this->arg('id', 0);
        if (!$id) exit(json_encode(array('info' => '参数错误', 'status' => 'n')));
        \ZhiCms\ext\WordCheck::delWord($id);
        \ZhiCms\ext\AdminLog::write('word', '删除了违规词 ID：' . $id);
        exit(json_encode(array('info' => '已删除', 'status' => 'y')));
    }

    /**
     * 实时检测一段文本
     */
    public function check(){
        $this->checkManageSession();
        $text = $this->arg('text', '');
        $res = \ZhiCms\ext\WordCheck::check($text, $this->cfg());
        exit(json_encode(array(
            'ok'   => $res['ok'],
            'hits' => $res['hits'],
            'local'=> $res['local'],
            'api'  => $res['api'],
        )));
    }

    /**
     * 扫描内容库中的违规词
     */
    public function scan(){
        $this->checkManageSession();
        $scope = $this->arg('scope', 'yun_article');
        if (!isset($this->scopes[$scope])) exit(json_encode(array('info' => '未知范围', 'status' => 'n')));

        $cfg = $this->cfg();
        try {
            $rows = obj('api/ApiData')->thisQuery("SELECT * FROM `{$scope}` ORDER BY `id` DESC LIMIT 500");
        } catch (\Throwable $e) {
            exit(json_encode(array('info' => '扫描失败：' . $e->getMessage(), 'status' => 'n')));
        }

        $hitList = array();
        $scanFields = array('title', 'content', 'intro', 'name');
        foreach ($rows as $row) {
            $text = '';
            foreach ($scanFields as $f) {
                if (isset($row[$f])) $text .= ' ' . $row[$f];
            }
            $res = \ZhiCms\ext\WordCheck::check($text, $cfg);
            if (!$res['ok']) {
                $hitList[] = array(
                    'id'    => isset($row['id']) ? $row['id'] : 0,
                    'title' => isset($row['title']) ? mb_substr($row['title'], 0, 60) : (isset($row['name']) ? mb_substr($row['name'], 0, 60) : '#' . (isset($row['id']) ? $row['id'] : '')),
                    'hits'  => $res['hits'],
                );
            }
        }
        exit(json_encode(array('status' => 'y', 'count' => count($hitList), 'list' => $hitList)));
    }
}
