<?php
/*
 * Functions to support user operations
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
 * @copyright  2008-2020, PhreeSoft, Inc.
 * @license    http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @version    4.x Last Update: 2020-09-03
 * @filesource lib/controller/module/bizuno/users.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_LIB ."controller/module/bizuno/functions.php", 'getIcons', 'function');
bizAutoLoad(BIZUNO_ROOT."portal/guest.php", 'guest');

class bizunoUsers
{
    public $moduleID = 'bizuno';

    function __construct()
    {
        $this->lang     = getLang($this->moduleID);
        $this->helpIndex= "users";
    }

    /**
     * Main entry point structure for Bizuno users
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function manager(&$layout)
    {
        if (!$security = validateSecurity('bizuno', 'users', 1)) { return; }
        $title = lang('bizuno_users');
        $layout = array_replace_recursive($layout, viewMain(), [
            'title'=> $title,
            'divs' => [
                'heading'=> ['order'=>30, 'type'=>'html',     'html'=>"<h1>$title</h1>\n"],
                'roles'  => ['order'=>60, 'type'=>'accordion','key' =>'accUsers']],
            'accordion'=> ['accUsers'=>  ['divs'=>  [
                'divUsersManager'=> ['order'=>30,'label'=>lang('manager'),'type'=>'datagrid','key'=>'dgUsers'],
                'divUsersDetail' => ['order'=>70,'label'=>lang('details'),'type'=>'html','html'=>'&nbsp;']]]],
            'datagrid'=> ['dgUsers' => $this->dgUsers('dgUsers', $security)]]);
    }

    /**
     * Lists the users with applied filters from user
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function managerRows(&$layout)
    {
        if (!$security = validateSecurity('bizuno', 'users', 1)) { return; }
        $layout= array_replace_recursive($layout, ['type'=>'datagrid','key'=>'dgUsers','datagrid'=>['dgUsers'=>$this->dgUsers('dgUsers', $security)]]);
    }

    /**
     * saves user selections in cache for page re-entry
     */
    private function managerSettings()
    {
        $data = ['path'=>'bizunoUsers','values' => [
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')],
            ['index'=>'page',  'clean'=>'integer','default'=>1],
            ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX."users.title"],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC'],
            ['index'=>'f0',    'clean'=>'char',   'default'=>'y'],
            ['index'=>'search','clean'=>'text',   'default'=>'']]];
        $this->defaults = updateSelection($data);
    }

    /**
     * structure to edit a user
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function edit(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$security = validateSecurity('bizuno', 'users', 1)) { return; }
        $structure = dbLoadStructure(BIZUNO_DB_PREFIX."users");
        if ($rID) { $dbData = dbGetRow(BIZUNO_DB_PREFIX."users", "admin_id='$rID'"); }
        else      { $dbData = ['settings'=>json_encode(['profile'=>['store_id'=>getUserCache('profile', 'store_id', false, 0)]])]; } // new user
        $mySettings= json_decode($dbData['settings'], true);
        $settings  = isset($mySettings['profile']) ? $mySettings['profile'] : [];
        msgDebug("\nSettings decoded is: ".print_r($settings, true));
        unset($dbData['settings']);
        dbStructureFill($structure, $dbData);
        $fldAcct   = ['admin_id','email','inactive','title','role_id','contact_id'];
        $fldProp   = ['icons','theme','cols']; // 'menu','restrict_user','store_id','restrict_store',
        $eKeys     = ['smtp_enable', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass'];
        $cID       = !empty($structure['contact_id']['attr']['value']) ? (int)$structure['contact_id']['attr']['value'] : 0;
        $name      = $cID ? dbGetValue(BIZUNO_DB_PREFIX."address_book", 'primary_name', "ref_id=$cID AND type='m'") : '';
        $title     = lang('bizuno_users').' - '.($rID ? $dbData['email'] : lang('new'));
        $fields    = $this->getViewUsers($structure, $settings);
        if (validateSecurity('bizuno', 'users', 3)) {
            $fields['password_new']    = ['order'=>55,'break'=>true,'label'=>lang('password_new'),    'attr'=>['type'=>'password']];
            $fields['password_confirm']= ['order'=>60,'break'=>true,'label'=>lang('password_confirm'),'attr'=>['type'=>'password']];
            $fldAcct[] = 'password_new';
            $fldAcct[] = 'password_confirm';
        }
        $data = ['type'=>'divHTML',
            'divs'    => ['detail'=>['order'=>50,'type'=>'divs','divs'=>[
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key' =>'tbUsers'],
                'head'   => ['order'=>15,'type'=>'html',   'html'=>"<h1>$title</h1>"],
                'frmBOF' => ['order'=>20,'type'=>'form',   'key' =>'frmUsers'],
                'body'   => ['order'=>50,'type'=>'tabs',   'key' =>'tabUsers'],
                'frmEOF' => ['order'=>90,'type'=>'html',   'html'=>"</form>"]]]],
            'toolbars'=> ['tbUsers'=>['icons'=>[
                'save'=> ['order'=>20,'hidden'=>$security>1?'0':'1',   'events'=>['onClick'=>"jq('#frmUsers').submit();"]],
                'new' => ['order'=>40,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"accordionEdit('accUsers', 'dgUsers', 'divUsersDetail', '".jsLang('details')."', 'bizuno/users/edit', 0);"]],
                'help'=> ['order'=>99,'icon'=>'help','label'=>lang('help'),'align'=>'right','hideLabel'=>true,'index'=>$this->helpIndex]]]],
            'tabs'=> ['tabUsers'=>['divs'=>[
                'general' => ['order'=>10,'label'=>lang('general'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'genAcct' => ['order'=>10,'type'=>'panel','key'=>'genAcct','classes'=>['block33']],
                    'genProp' => ['order'=>40,'type'=>'panel','key'=>'genProp','classes'=>['block33']],
                    'genAtch' => ['order'=>80,'type'=>'panel','key'=>'genAtch','classes'=>['block66']]]],
                'email'  => ['order'=>40,'label'=>lang('email'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'pnlEmail'=> ['order'=>40,'type'=>'panel','key'=>'pnlEmail','classes'=>['block50']]]]]]],
            'panels' => [
                'genAcct' => ['label'=>lang('account'),   'type'=>'fields','keys'=>$fldAcct],
                'genProp' => ['label'=>lang('properties'),'type'=>'fields','keys'=>$fldProp],
                'genAtch' => ['type'=>'attach','defaults'=>['path'=>getModuleCache($this->moduleID,'properties','usersAttachPath'),'prefix'=>"rID_{$rID}_"]],
                'pnlEmail'=> ['label'=>lang('settings'),  'type'=>'fields','keys'=>$eKeys]],
            'forms'   => ['frmUsers'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=bizuno/users/save"]]],
            'fields'  => $fields,
            'text'    => ['pw_title' => $rID?lang('password_lost'):lang('password')],
            'jsHead'  => ['init'=>"var usersContact = ".json_encode([['id'=>$cID, 'primary_name'=>$name]]).";"],
            'jsReady' => ['init'=>"ajaxForm('frmUsers');"]];
        $data['settings'] = $mySettings; // pass for customization
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Generates the field list for the user edit view
     * @param array $fields
     * @param type $settings
     * @return array
     */
    private function getViewUsers($fields, $settings)
    {
        $stores= getModuleCache('bizuno', 'stores');
        array_unshift($stores, ['id'=>-1, 'text'=>lang('all')]);
        $defs  = ['type'=>'e', 'data'=>'usersContact', 'callback'=>''];
        $fields['role_id']['attr']['type']= 'select';
        $output= [
            'admin_id'      => $fields['admin_id'],
            'email'         => array_merge($fields['email'],   ['order'=>15,'break'=>true]),
            'inactive'      => array_merge($fields['inactive'],['order'=>20,'break'=>true]),
            'title'         => array_merge($fields['title'],   ['order'=>25,'break'=>true]),
            'role_id'       => array_merge($fields['role_id'], ['order'=>30,'break'=>true,'values'=>listRoles(true, false, false)]),
            'contact_id'    => ['order'=>35,'break'=>true,'label'=>lang('contacts_rep_id_i'),'defaults'=>$defs,'attr'=>['type'=>'contact','value'=>$fields['contact_id']['attr']['value']]],
            'icons'         => ['order'=>65,'break'=>true,'label'=>$this->lang['icon_set'],'values'=>getIcons(), 'attr'=>['type'=>'select','value'=>isset($settings['icons'])?$settings['icons']:'default']],
            'theme'         => ['order'=>70,'break'=>true,'label'=>lang('theme'),          'values'=>getThemes(),'attr'=>['type'=>'select','value'=>isset($settings['theme'])?$settings['theme']:'default']],
            'cols'          => ['order'=>80,'break'=>true,'label'=>$this->lang['dashboard_columns'],'attr'=>['value'=>isset($settings['cols'])?$settings['cols']:3]],
//          'mail_enable'   => ['order'=>10,'break'=>true,'label'=>$this->lang['mail_enable_lbl'],'tip'=>$this->lang['mail_enable_tip'],'attr'=>['type'=>'selNoYes','value'=>isset($settings['mail_enable'])?$settings['mail_enable']:1]],
            'smtp_enable'   => ['order'=>20,'break'=>true,'label'=>$this->lang['smtp_enable_lbl'],'tip'=>$this->lang['smtp_enable_tip'],'attr'=>['type'=>'selNoYes','value'=>isset($settings['smtp_enable'])?$settings['smtp_enable']:0]],
            'smtp_host'     => ['order'=>30,'break'=>true,'label'=>$this->lang['smtp_host_lbl'],  'tip'=>$this->lang['smtp_host_tip'],  'attr'=>['value'=>isset($settings['smtp_host'])?$settings['smtp_host']:'smtp.gmail.com']],
            'smtp_port'     => ['order'=>40,'break'=>true,'label'=>$this->lang['smtp_port_lbl'],  'tip'=>$this->lang['smtp_port_tip'],  'attr'=>['type'=>'integer' ,'value'=>isset($settings['smtp_port'])?$settings['smtp_port']:587]],
            'smtp_user'     => ['order'=>50,'break'=>true,'label'=>$this->lang['smtp_user_lbl'],'attr'=>['value'=>isset($settings['smtp_user'])?$settings['smtp_user']:'']],
            'smtp_pass'     => ['order'=>60,'break'=>true,'label'=>$this->lang['smtp_pass_lbl'],'attr'=>['type'=>'password','value'=>'']]];
        return $output;
    }

    /**
     * This method saves the users data and updates the portal if required.
     * @return Post save action, refresh grid, clear form
     */
    public function save(&$layout=[])
    {
        global $io;
        $rID   = clean('admin_id','integer','post');
        if (!$security = validateSecurity('bizuno', 'users', $rID?3:2)) { return; }
        $email = clean('email',   'email',  'post');
        $values= requestData(dbLoadStructure(BIZUNO_DB_PREFIX."users"));
        if (!$rID) {
            $dup = dbGetValue(BIZUNO_DB_PREFIX."users", 'admin_id', "email='".addslashes($email)."' AND admin_id<>$rID");
            if ($dup) { return msgAdd(lang('error_duplicate_id')); }
            $oldEmail = false;
        } else {
            $oldEmail = dbGetValue(BIZUNO_DB_PREFIX."users", 'email', "admin_id=$rID");
        }
        if (!isset($values['role_id']) || !$values['role_id']) { return msgAdd($this->lang['err_role_undefined']); }
        // build the users default settings
        $settings = $rID ? json_decode(dbGetValue(BIZUNO_DB_PREFIX."users", 'settings', "admin_id=$rID"), true) : [];
        if (empty($settings['profile']['smtp_pass'])) { $settings['profile']['smtp_pass'] = ''; }
        $settings['profile']['icons']         = clean('icons',         'text',   'post');
        $settings['profile']['theme']         = clean('theme',         'text',   'post');
        $settings['profile']['menu']          = clean('menu',          'text',   'post'); // menu position
        $settings['profile']['cols']          = clean('cols',          'integer','post'); // # of dashboard columns
//        $settings['profile']['store_id']      = clean('store_id',      'integer','post'); // home store for granularity
//        $settings['profile']['restrict_store']= clean('restrict_store','integer','post'); // restrict to store
//        $settings['profile']['restrict_user'] = clean('restrict_user', 'integer','post'); // restrict user
        $settings['profile']['smtp_enable']   = clean('smtp_enable',   'boolean','post');
        $settings['profile']['smtp_host']     = clean('smtp_host',     'url',    'post');
        $settings['profile']['smtp_port']     = clean('smtp_port',     'integer','post');
        $settings['profile']['smtp_user']     = clean('smtp_user',     'email',  'post');
        $password = clean('smtp_pass', 'text', 'post');
        $settings['profile']['smtp_pass']     = !empty($password) ? $password : $settings['profile']['smtp_pass'];
        $values['settings']  = json_encode($settings);
        // update the local users table with details
        $newID = dbWrite(BIZUNO_DB_PREFIX.'users', $values, $rID?'update':'insert', "admin_id=$rID");
        // check role attributes for more processing
        $role = dbGetRow(BIZUNO_DB_PREFIX."roles", "id={$values['role_id']}");
        msgDebug("\nRead role = ".print_r($role, true));
        $role['settings'] = json_decode($role['settings'], true);
        if (!$values['inactive'] && empty($role['settings']['restrict'])) {
            msgDebug("\nGoing to save user on portal.");
            $pw_new = clean('password_new',    'password','post');
            $pw_eql = clean('password_confirm','password','post');
            if (strlen($pw_new) > 0) { // check, see if reset password
                $guest  = new guest();
                $pw_enc = $guest->passwordReset($pw_new, $pw_eql);
                if ($pw_enc) { $values['biz_pass'] = $pw_enc; }
            }
            $portal = new guest();
            $portal->portalSaveUser($values, $rID?false:true);
            if ($oldEmail <> $values['email']) { portalDelete($oldEmail); }
        } elseif ($values['inactive']) {
            msgAdd("\nThis users account will be denied access through the portal.", 'caution');
            portalDelete($email); // disable access through the portal
        }
        if (!$rID) { $rID = $_POST['admin_id'] = $newID; }
        msgDebug("\nrID = $rID and session admin_id = ".getUserCache('profile', 'admin_id', false, 0));
        if ($io->uploadSave('file_attach', getModuleCache('bizuno', 'properties', 'usersAttachPath')."rID_{$rID}_")) {
            dbWrite(BIZUNO_DB_PREFIX.'users', ['attach'=>1], 'update', "id=$rID");
        }
        msgLog(lang('table')." users - ".lang('save')." {$values['email']} ($rID)");
        msgAdd(lang('msg_database_write'), 'success');
        $data = ['content'=>['action'=>'eval','actionData'=>"jq('#accUsers').accordion('select',0); bizGridReload('dgUsers');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Copies a user to a new username
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function copy(&$layout=[])
    {
        $rID  = clean('rID',  'integer', 'get');
        if (!$security = validateSecurity('bizuno', 'users', $rID?3:2)) { return; }
        $this->security = getUserCache('security');
        $email= clean('data', 'email', 'get');
        if (!$rID || !$email) { return msgAdd(lang('err_copy_name_prompt')); }
        $user = dbGetRow(BIZUNO_DB_PREFIX."users", "admin_id='$rID'");
        // copy user at the portal
        $pData= portalRead('users', "biz_user='{$user['email']}'");
        unset($pData['id']);
        unset($pData['date_updated']);
        $pData['date_created']= date('Y-m-d H:i:s');
        $pData['biz_user']    = $email;
        portalWrite('users', $pData);
        unset($user['admin_id']);
        $user['email'] = $email;
        $nID  = $_GET['rID'] = dbWrite(BIZUNO_DB_PREFIX."users", $user);
        if ($nID) { msgLog(lang('table')." users-".lang('copy').": $email ($rID => $nID)"); }
        $data = ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dgUsers'); accordionEdit('accUsers', 'dgUsers', 'divUsersDetail', '".jsLang('details')."', 'bizuno/users/edit', $nID);"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Deletes a user and removes them from the portal
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'users', 4)) { return; }
        $rID  = clean('rID', 'integer', 'get');
        $this->security = getUserCache('security');
        if (!$rID) { return msgAdd(lang('err_copy_name_prompt')); }
        if (getUserCache('profile', 'admin_id', false, 0) == $rID) { return msgAdd($this->lang['err_delete_user']); }
        $email= dbGetValue(BIZUNO_DB_PREFIX."users", 'email', "admin_id='$rID'");
        $data = ['content'=> ['action'=>'eval', 'actionData'   =>"bizGridReload('dgUsers');"],
            'dbAction'    => [BIZUNO_DB_PREFIX."users"         => "DELETE FROM ".BIZUNO_DB_PREFIX."users WHERE admin_id='$rID'",
                              BIZUNO_DB_PREFIX."users_profiles"=> "DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE user_id='$rID'"]];
        portalDelete($email);
        $io = new \bizuno\io();
        $io->fileDelete(getModuleCache('bizuno', 'properties', 'usersAttachPath')."rID_{$rID}_*");
        msgLog(lang('table')." users-".lang('delete')." $email ($rID)");
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Datagrid structure for Bizuno users
     * @param string $name - DOM field name
     * @param integer $security - users defined security level
     * @return array - datagrid structure
     */
    private function dgUsers($name, $security=0)
    {
        $this->managerSettings();
        $yes_no_choices = [['id'=>'a','text'=>lang('all')], ['id'=>'y','text'=>lang('active')], ['id'=>'n','text'=>lang('inactive')]];
        // clean up the filter sqls
        if (!isset($this->defaults['f0'])) { $this->defaults['f0'] = 'y'; }
        switch ($this->defaults['f0']) {
            default:
            case 'a': $f0_value = ""; break;
            case 'y': $f0_value = BIZUNO_DB_PREFIX."users.inactive<>'1'"; break;
            case 'n': $f0_value = BIZUNO_DB_PREFIX."users.inactive='1'";  break;
        }
        return ['id'=>$name, 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'],
            'strict' => true, // forces limit of the fields read to columns listed, roles inactive is overwriting users inactive
            'attr'   => ['toolbar'=>"#{$name}Toolbar",'idField'=>'admin_id', 'url'=>BIZUNO_AJAX."&bizRt=bizuno/users/managerRows"],
            'events' => [
                'rowStyler'    => "function(index, row) { if (row.inactive == '1') { return {class:'row-inactive'}; }}",
                'onDblClickRow'=> "function(rowIndex, rowData){ accordionEdit('accUsers', 'dgUsers', 'divUsersDetail', '".lang('details')."', 'bizuno/users/edit', rowData.admin_id); }"],
            'source' => [
                'tables' => [
                    'users' => ['table'=>BIZUNO_DB_PREFIX."users", 'join'=>'',    'links'=>''],
                    'roles' => ['table'=>BIZUNO_DB_PREFIX."roles", 'join'=>'join','links'=>BIZUNO_DB_PREFIX."roles.id=".BIZUNO_DB_PREFIX."users.role_id"]],
                'actions' => [
                    'newUser'  => ['order'=>10,'icon'=>'new',  'events'=>['onClick'=>"accordionEdit('accUsers', 'dgUsers', 'divUsersDetail', '".lang('details')."', 'bizuno/users/edit', 0);"]],
                    'clrSearch'=> ['order'=>50,'icon'=>'clear','events'=>['onClick'=>"bizTextSet('search', ''); ".$name."Reload();"]],
                    'help'     => ['order'=>99,'icon'=>'help', 'label' =>lang('help'),'align'=>'right','hideLabel'=>true,'index'=>$this->helpIndex]],
                'search' => [BIZUNO_DB_PREFIX."users.email", BIZUNO_DB_PREFIX."roles".'.title'],
                'sort'   => ['s0'=>  ['order'=>10, 'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]],
                'filters'=> [
                    'f0'     => ['order'=>10,'sql'=>$f0_value,'label'=>lang('status'), 'values'=>$yes_no_choices, 'attr'=>  ['type'=>'select', 'value'=>$this->defaults['f0']]],
                    'search' => ['order'=>90,'attr'=>['value'=>$this->defaults['search']]]]],
            'columns' => [
                'admin_id'=> ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."users.admin_id",'attr'=>['hidden'=>true]],
                'inactive'=> ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."users.inactive",'attr'=>['hidden'=>true]],
                'action'  => ['order'=>1, 'label'=>lang('action'),'events'=>['formatter'=>$name.'Formatter'],
                    'actions'=> [
                        'edit'  => ['order'=>20,'icon'=>'edit',
                            'events'=>['onClick'=>"accordionEdit('accUsers', 'dgUsers', 'divUsersDetail', '".lang('details')."', 'bizuno/users/edit', idTBD);"]],
                        'copy'  => ['order'=>40,'icon'=>'copy', 'hidden'=>$security>1?false:true,
                            'events'=>['onClick'=>"var title=prompt('".lang('msg_copy_name_prompt')."'); jsonAction('bizuno/users/copy', idTBD, title);"]],
                        'delete'=> ['order'=>90,'icon'=>'trash','hidden'=>$security>3?false:true,
                            'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('bizuno/users/delete', idTBD);"]]]],
                'email'   => ['order'=>10, 'field' => BIZUNO_DB_PREFIX."users.email", 'label'=>pullTableLabel(BIZUNO_DB_PREFIX."users", 'email'),
                    'attr'=> ['width'=>120, 'sortable'=>true, 'resizable'=>true]],
                'title'   => ['order'=>20, 'field' => BIZUNO_DB_PREFIX."users.title", 'label'=>lang('title'),
                    'attr'=> ['width'=>120, 'sortable'=>true, 'resizable'=>true]],
                'role_id' => ['order'=>30, 'field' => BIZUNO_DB_PREFIX."roles.title", 'label'=>lang('role'),
                    'attr'=> ['width'=>120, 'sortable'=>true, 'resizable'=>true]]]];
    }
}
