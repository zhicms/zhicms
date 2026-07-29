<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class ForumController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /**
     * 社区总览：帖子列表
     */
    public function index(){
        $this->checkManageSession();
        $this->pageText = array("社区管理", "帖子列表");

        $where = array("1");
        $keyword = $this->arg("keyword", '');
        if ($keyword) {
            $safeKey = addslashes($keyword);
            $where = array("`title` LIKE '%{$safeKey}%' OR `content` LIKE '%{$safeKey}%'");
        }
        $baseUrl = "index.php?r=manage/forum/index";
        if ($keyword) $baseUrl .= "&keyword=" . urlencode($keyword);

        $page = obj('api/ApiData')->page("30", "yun_forum", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
        $this->ret = $page['list'];
        $this->keyword = $keyword;
        $this->display();
    }

    /**
     * 帖子删除
     */
    public function delete(){
        $this->checkManageSession();
        $id = intval($this->arg("id"));
        if ($id <= 0) exit(json_encode(array("info" => "参数错误", "status" => "n")));

        $where = "id = ?";
        obj('api/ApiData')->deleteThis("yun_forum", $where, array($id));
        // 同时删除关联回复
        obj("api/ApiData")->executeQuery("DELETE FROM `{pre}forum_reply` WHERE `forum_id` = ?", array($id));

        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }

    /**
     * 帖子显示/隐藏切换
     */
    public function toggleStatus(){
        $this->checkManageSession();
        $id = intval($this->arg("id"));
        if ($id <= 0) exit(json_encode(array("info" => "参数错误", "status" => "n")));

        $row = obj("api/ApiData")->thisQuery("SELECT `status` FROM `{pre}forum` WHERE `id` = ? LIMIT 1", array($id));
        $newStatus = (!empty($row) && $row[0]['status'] == 1) ? 0 : 1;
        obj("api/ApiData")->executeQuery("UPDATE `{pre}forum` SET `status` = ? WHERE `id` = ?", array($newStatus, $id));

        exit(json_encode(array("info" => "操作成功", "status" => "y", "status_val" => $newStatus)));
    }

    /**
     * 帖子详情 + 回复管理
     */
    public function view(){
        $this->checkManageSession();
        $this->pageText = array("社区管理", "帖子详情");

        $id = intval($this->arg("id"));
        if ($id <= 0) { $this->redirect("index.php?r=manage/forum/index"); return; }

        $where = array("`id` = {$id}");
        $forum = obj("api/ApiData")->dataSelect("yun_forum", $where);
        if (empty($forum)) { $this->redirect("index.php?r=manage/forum/index"); return; }
        $this->forum = $forum[0];

        $replies = obj("api/ApiData")->thisQuery(
            "SELECT * FROM `{pre}forum_reply` WHERE `forum_id` = ? ORDER BY `id` ASC",
            array($id)
        );
        $this->replies = $replies ? $replies : array();
        $this->display();
    }

    /**
     * 删除回复
     */
    public function deleteReply(){
        $this->checkManageSession();
        $id = intval($this->arg("id"));
        if ($id <= 0) exit(json_encode(array("info" => "参数错误", "status" => "n")));

        $row = obj("api/ApiData")->thisQuery("SELECT `forum_id` FROM `{pre}forum_reply` WHERE `id` = ? LIMIT 1", array($id));
        if (empty($row)) exit(json_encode(array("info" => "回复不存在", "status" => "n")));

        obj("api/ApiData")->executeQuery("DELETE FROM `{pre}forum_reply` WHERE `id` = ?", array($id));
        // 更新帖子回复数
        $forumId = (int)$row[0]['forum_id'];
        obj("api/ApiData")->executeQuery(
            "UPDATE `{pre}forum` SET `reply_count` = (SELECT COUNT(*) FROM `{pre}forum_reply` WHERE `forum_id` = ? AND `hide` = 'n') WHERE `id` = ?",
            array($forumId, $forumId)
        );

        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }

    /**
     * 板块管理
     */
    public function board(){
        $this->checkManageSession();
        $this->pageText = array("社区管理", "板块管理");

        $boards = obj("api/ApiData")->dataSelect("yun_bankuai", array("1"), "`px` ASC, `id` ASC");
        $this->boards = $boards ? $boards : array();
        $this->display();
    }

    /**
     * 板块保存（新增/编辑）
     */
    public function saveBoard(){
        $this->checkManageSession();
        if (!IS_POST) exit(json_encode(array("info" => "请求方式错误", "status" => "n")));

        $id = intval($this->arg("id"));
        $name = trim($this->arg("name"));
        $px = intval($this->arg("px"));

        if ($name === '') exit(json_encode(array("info" => "请填写板块名", "status" => "n")));
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        if ($id > 0) {
            $where = array("`id` = {$id}");
            $data = array('name' => $name, 'px' => $px);
            obj('api/ApiData')->dataUpdate("yun_bankuai", $data, $where);
        } else {
            $data = array('name' => $name, 'px' => $px);
            obj('api/ApiData')->insertData("yun_bankuai", $data);
        }
        exit(json_encode(array("info" => "保存成功", "status" => "y")));
    }

    /**
     * 板块删除
     */
    public function deleteBoard(){
        $this->checkManageSession();
        $id = intval($this->arg("id"));
        if ($id <= 0) exit(json_encode(array("info" => "参数错误", "status" => "n")));

        obj("api/ApiData")->executeQuery("DELETE FROM `{pre}bankuai` WHERE `id` = ?", array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }

    /**
     * 小组管理
     */
    public function group(){
        $this->checkManageSession();
        $this->pageText = array("社区管理", "小组管理");

        $groups = obj("api/ApiData")->dataSelect("yun_group", array("1"), "`px` ASC, `id` ASC");
        $this->groups = $groups ? $groups : array();

        // 板块列表（用于下拉选择）
        $boards = obj("api/ApiData")->dataSelect("yun_bankuai", array("1"), "`px` ASC, `id` ASC");
        $this->boards = $boards ? $boards : array();

        $this->display();
    }

    /**
     * 小组保存（v2 支持板块归属、图标、简介）
     */
    public function saveGroup(){
        $this->checkManageSession();
        if (!IS_POST) exit(json_encode(array("info" => "请求方式错误", "status" => "n")));

        $id = intval($this->arg("id"));
        $name = trim($this->arg("groupname"));
        $px = intval($this->arg("px"));
        $bankuaiId = intval($this->arg("bankuai_id"));
        $icon = trim($this->arg("icon"));
        $desc = trim($this->arg("desc"));

        if ($name === '') exit(json_encode(array("info" => "请填写小组名", "status" => "n")));
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
        // icon 仅接受 upload/ 路径或 http(s):// 开头
        if ($icon !== '' && !preg_match('#^https?://#i', $icon) && strpos($icon, 'upload/') !== 0) {
            $icon = '';
        }

        $data = array(
            'groupname' => $name,
            'bankuai_id' => $bankuaiId,
            'px' => $px,
            'icon' => $icon,
            'desc' => $desc,
        );

        if ($id > 0) {
            $where = array("`id` = {$id}");
            obj('api/ApiData')->dataUpdate("yun_group", $data, $where);
        } else {
            $data['member_count'] = 0;
            obj('api/ApiData')->insertData("yun_group", $data);
        }
        exit(json_encode(array("info" => "保存成功", "status" => "y")));
    }

    /**
     * 小组删除
     */
    public function deleteGroup(){
        $this->checkManageSession();
        $id = intval($this->arg("id"));
        if ($id <= 0) exit(json_encode(array("info" => "参数错误", "status" => "n")));

        obj("api/ApiData")->executeQuery("DELETE FROM `{pre}group` WHERE `id` = ?", array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }

    /**
     * 评论管理（支持模型筛选 + 关键词搜索）
     */
    public function comment(){
        $this->checkManageSession();
        $this->pageText = array("社区管理", "评论管理");

        $where = array("1");

        $modelFilter = $this->arg("model", '');
        $keyword = $this->arg("keyword", '');
        $this->modelFilter = $modelFilter;
        $this->keyword = $keyword;

        if ($modelFilter !== '' && in_array((string)$modelFilter, array('1','2','3','4','5'), true)) {
            $safeModel = (int)$modelFilter;
            $where = array("`model` = {$safeModel}");
        }
        if ($keyword !== '') {
            $safeKey = addslashes($keyword);
            if ($where === array("1")) {
                $where = array("`content` LIKE '%{$safeKey}%'");
            } else {
                $where[0] .= " AND `content` LIKE '%{$safeKey}%'";
            }
        }

        $baseUrl = "index.php?r=manage/forum/comment";
        if ($modelFilter !== '') $baseUrl .= "&model=" . urlencode($modelFilter);
        if ($keyword !== '') $baseUrl .= "&keyword=" . urlencode($keyword);

        // model 已通过 in_array 严格校验为 '1'..'5'，可安全转 int 拼接
        $page = obj('api/ApiData')->page("30", "yun_comment", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
        $this->ret = $page['list'];
        $this->display();
    }

    /**
     * 评论删除
     */
    public function deleteComment(){
        $this->checkManageSession();
        $id = intval($this->arg("id"));
        if ($id <= 0) exit(json_encode(array("info" => "参数错误", "status" => "n")));

        obj("api/ApiData")->executeQuery("DELETE FROM `{pre}comment` WHERE `id` = ?", array($id));
        exit(json_encode(array("info" => "删除成功", "status" => "y")));
    }

    /**
     * 评论显示/隐藏切换
     */
    public function toggleCommentHide(){
        $this->checkManageSession();
        $id = intval($this->arg("id"));
        if ($id <= 0) exit(json_encode(array("info" => "参数错误", "status" => "n")));

        $row = obj("api/ApiData")->thisQuery("SELECT `hide` FROM `{pre}comment` WHERE `id` = ? LIMIT 1", array($id));
        $newHide = (!empty($row) && $row[0]['hide'] === 'n') ? 'y' : 'n';
        obj("api/ApiData")->executeQuery("UPDATE `{pre}comment` SET `hide` = ? WHERE `id` = ?", array($newHide, $id));

        exit(json_encode(array("info" => "操作成功", "status" => "y", "hide" => $newHide)));
    }

    /**
     * 评论置顶切换
     */
    public function toggleCommentTop(){
        $this->checkManageSession();
        $id = intval($this->arg("id"));
        if ($id <= 0) exit(json_encode(array("info" => "参数错误", "status" => "n")));

        $row = obj("api/ApiData")->thisQuery("SELECT `top` FROM `{pre}comment` WHERE `id` = ? LIMIT 1", array($id));
        $newTop = (!empty($row) && $row[0]['top'] === 'n') ? 'y' : 'n';
        obj("api/ApiData")->executeQuery("UPDATE `{pre}comment` SET `top` = ? WHERE `id` = ?", array($newTop, $id));

        exit(json_encode(array("info" => "操作成功", "status" => "y", "top" => $newTop)));
    }
}
