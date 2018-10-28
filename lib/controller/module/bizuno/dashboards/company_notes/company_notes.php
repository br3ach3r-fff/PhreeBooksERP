<?php
/*
 * Bizuno dashboard - Company Notes
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.TXT.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please refer to http://www.phreesoft.com for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2018, PhreeSoft, Inc.
 * @license    http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @version    3.x Last Update: 2018-10-10
 * @filesource /lib/controller/module/bizuno/dashboards/company_notes/company_notes.php
 */

namespace bizuno;

define('DASHBOARD_COMPANY_NOTES_VERSION','3.1');

class company_notes 
{
    public $moduleID = 'bizuno';
    public $methodDir= 'dashboards';
    public $code     = 'company_notes';
    public $category = 'general';
	
	function __construct($settings)
    {
		$this->security= 1;
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
        $defaults      = ['users'=>'-1','roles'=>'-1'];
        $this->settings= array_replace_recursive($defaults, $settings);
	}

    public function settingsStructure()
    {
        return [
            'users' => ['label'=>lang('users'), 'position'=>'after','values'=>listUsers(),'attr'=>['type'=>'select','value'=>$this->settings['users'],'size'=>10,'multiple'=>'multiple']],
            'roles' => ['label'=>lang('groups'),'position'=>'after','values'=>listRoles(),'attr'=>['type'=>'select','value'=>$this->settings['roles'],'size'=>10,'multiple'=>'multiple']]];
	}

    public function install($moduleID = '', $menu_id = '')
    {
        $settings = dbGetValue(BIZUNO_DB_PREFIX."users_profiles", 'settings', "dashboard_id='$this->code'");
        $sql_data = [
            'user_id'     => getUserCache('profile', 'admin_id', false, 0),
            'menu_id'     => $menu_id,
            'module_id'   => $moduleID,
            'dashboard_id'=> $this->code,
            'column_id'   => 0,
            'row_id'      => 0,
            'settings'    => $settings];
        dbWrite(BIZUNO_DB_PREFIX."users_profiles", $sql_data);
    }

    public function render()
    {
        $security = getUserCache('security', 'admin', false, 0);
        $data = [
            $this->code.'_0'     =>['label'=>lang('note'),'classes'=>['easyui-validatebox'],'attr'=>['type'=>'text','required'=>'true','size'=>50]],
            $this->code.'_button'=>['attr'=>['type'=>'button','value'=>lang('new')],'styles'=>['cursor'=>'pointer'],'events'=>['onClick'=>"dashboardAttr('$this->moduleID:$this->code', 0);"]]];
        $html  = '<div>';
        $html .= '  <div id="'.$this->code.'_attr" style="display:none">';
        if ($security > 1) {
            $html .= '    <form id="'.$this->code.'Form" action="">';
            $html .= '      <div style="white-space:nowrap">'.html5($this->code.'_0',      $data[$this->code.'_0']).'</div>';
            $html .= '      <div style="text-align:right;">' .html5($this->code.'_button', $data[$this->code.'_button']).'</div>';
            $html .= '    </form>';
        }
        $html .= '  </div>';
        // Build content box
        $index = 1;
        if (!isset($this->settings['data'])) { unset($this->settings['users']); unset($this->settings['roles']); $this->settings = ['data' => $this->settings]; } // OLD WAY
        $html .= html5('', ['classes'=>['easyui-datalist'],'attr'=>['type'=>'ul']])."\n";
        if (!empty($this->settings['data'])) {
            foreach ($this->settings['data'] as $entry) {
                $html .= '<li><span style="float:left">'."&#9679; $entry".'</span>';
                if ($security > 2) { $html .= '<span style="float:right">'.html5('', ['icon'=>'trash','size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) dashboardAttr('$this->moduleID:$this->code', $index);"]]).'</span>'; }
                $html .= '</li>';
                $index++;
            }
        } else {
            $html .= '<li><span>'.lang('no_results')."</span></li>";
        }
        $html .= '</ul></div>';
        $js = '
jq("#'.$this->code.'Form").keypress(function(event) {
    var keycode = (event.keyCode ? event.keyCode : event.which);
    if (keycode == "13") dashboardAttr("'.$this->moduleID.':'.$this->code.'", 0);
});';
        return $html;
    }

    public function save()
    {
        if (getUserCache('security', 'admin', false, 0) < 2) { return msgAdd('Illegal Access!'); } 
        $menu_id  = clean('menuID', 'cmd', 'get');
        $rmID     = clean('rID', 'integer', 'get');
        $add_entry= clean($this->code.'_0', 'text', 'post');
        if (!$rmID && $add_entry == '') { return; } // do nothing if no title or url entered
        // fetch the current settings
        $result = dbGetRow(BIZUNO_DB_PREFIX."users_profiles", "menu_id='$menu_id' AND dashboard_id='$this->code'");
        $settings = json_decode($result['settings'], true);
        if (!isset($settings['data'])) { unset($settings['users']); unset($settings['roles']); $settings=['data'=>$settings]; } // OLD WAY
        if ($rmID) { array_splice($settings['data'], $rmID - 1, 1); }
        else       { $settings['data'][] = $add_entry; }
        dbWrite(BIZUNO_DB_PREFIX."users_profiles", ['settings'=>json_encode($settings)], 'update', "dashboard_id='$this->code'");
        return $result['id'];
    }
}
