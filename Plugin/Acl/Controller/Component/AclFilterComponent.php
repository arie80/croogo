<?php
/**
 * AclFilter Component
 *
 * PHP version 5
 *
 * @category Component
 * @package  Croogo
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class AclFilterComponent extends Component {

    protected $controller = null;

/**
 * @param object $controller controller
 * @param array  $settings   settings
 */
    public function initialize(&$controller) {
        $this->controller =& $controller;
    }

/**
 * acl and auth
 *
 * @return void
 */
    public function auth() {
        //Configure AuthComponent
        $userScope = array('User.status' => 1);
        $userScope = Set::merge($userScope, Configure::read('Acl.Auth.userScope'));
        $this->controller->Auth->authenticate = array(
            AuthComponent::ALL => array(
                'userModel' => 'User',
                'fields' => array(
                    'username' => 'username',
                    ),
                'scope' => $userScope,
                ),
            'Form',
            );
        $actionPath = 'controllers';
        $this->controller->Auth->authorize = array(
            AuthComponent::ALL => array('actionPath' => $actionPath),
            'Actions',
            );
        $this->controller->Auth->loginAction = array(

        $loginAction = array(
            'plugin' => null,
            'controller' => 'users',
            'action' => 'login',
        );
        if (!isset($this->controller->params['admin'])) {
            $loginAction = Set::merge($loginAction, Configure::read('Acl.Auth.loginAction'));
        }
        $this->controller->Auth->loginAction = $loginAction;

        $loginRedirect = array(
            'plugin' => null,
            'controller' => 'users',
            'action' => 'index',
        );
        $loginRedirect = Set::merge($loginRedirect, Configure::read('Acl.Auth.loginRedirect'));
        $this->controller->Auth->loginRedirect = $loginRedirect;

        if ($authError = Configure::read('Acl.Auth.authError')) {
            $this->controller->authError = $authError;
        }

        if ($this->controller->Auth->user() && $this->controller->Auth->user('role_id') == 1) {
            // Role: Admin
            $this->controller->Auth->allowedActions = array('*');
        } else {
            if ($this->controller->Auth->user()) {
                $roleId = $this->controller->Auth->user('role_id');
            } else {
                $roleId = 3; // Role: Public
            }

            $aro = $this->controller->Acl->Aro->find('first', array(
                'conditions' => array(
                    'Aro.model' => 'Role',
                    'Aro.foreign_key' => $roleId,
                ),
                'recursive' => -1,
            ));
            $aroId = $aro['Aro']['id'];
            $thisControllerNode = $this->controller->Acl->Aco->node($actionPath .'/'.  $this->controller->name);
            if ($thisControllerNode) {
                $thisControllerNode = $thisControllerNode['0'];
                $thisControllerActions = $this->controller->Acl->Aco->find('list', array(
                    'conditions' => array(
                        'Aco.parent_id' => $thisControllerNode['Aco']['id'],
                    ),
                    'fields' => array(
                        'Aco.id',
                        'Aco.alias',
                    ),
                    'recursive' => '-1',
                ));
                $thisControllerActionsIds = array_keys($thisControllerActions);
                $allowedActions = $this->controller->Acl->Aco->Permission->find('list', array(
                    'conditions' => array(
                        'Permission.aro_id' => $aroId,
                        'Permission.aco_id' => $thisControllerActionsIds,
                        'Permission._create' => 1,
                        'Permission._read' => 1,
                        'Permission._update' => 1,
                        'Permission._delete' => 1,
                    ),
                    'fields' => array(
                        'id',
                        'aco_id',
                    ),
                    'recursive' => '-1',
                ));
                $allowedActionsIds = array_values($allowedActions);
            }

            $allow = array();
            if (isset($allowedActionsIds) &&
                is_array($allowedActionsIds) &&
                count($allowedActionsIds) > 0) {
                foreach ($allowedActionsIds AS $i => $aId) {
                    $allow[] = $thisControllerActions[$aId];
                }
            }
            $this->controller->Auth->allowedActions = $allow;
        }
    }

}
?>