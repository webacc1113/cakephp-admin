<?php

App::uses('Component', 'Controller');

class AdminsComponent extends Component {
	
	public function admin($admin_id) {
		App::import('Model', 'Admin');
		$this->Admin = new Admin;
		$this->Admin->AdminRole->bindModel(array('belongsTo' => array('Role')));
		$this->Admin->AdminGroup->bindModel(array('belongsTo' => array('Group')));
		$admin = $this->Admin->find('first', array(
			'conditions' => array(
				'Admin.id' => $admin_id
			),
			'recursive' => 2
		));
		if (!$admin) {
			return false;
		}
		
		$roles = array('admin', 'users', 'reports', 'projects', 'transactions', 'campaigns');
		$admin_roles = array(
			'admin' => false,
			'users' => false,
			'reports' => false,
			'projects' => false,
			'transactions' => false,
			'campaigns' => false,
			'guest' => false
		);
		$permission_group_keys = array();
		foreach ($admin['AdminRole'] as $admin_role) {
			foreach ($roles as $role) {
				if ($admin_role['Role'][$role] == true) {
					$admin_roles[$role] = true;
				}
			}
			
			$permission_group_keys[] = $admin_role['Role']['key'];
		}
		
		// If role is Admin, assign him all the other roles, except 'guest'.
		if ($admin_roles['admin']) {
			foreach ($admin_roles as $key => $val) {
				if ($key == 'guest') {
					continue;
				}
				
				$admin_roles[$key] = true;
			}
		}
		
		// if all the roles are false, then its a guest role.
		// if any other role is specified, guest role is skipped. This will supperseed the functionality of other role over guest role.
		$guest = true;
		foreach ($admin_roles as $role) {
			if ($role) {
				$guest = false;
				break;
			}
		}
		
		if ($guest) {
			$admin_roles['guest'] = true;
		}
		
		$admin_groups = array();
		foreach ($admin['AdminGroup'] as $admin_group) {
			$admin_groups[] = $admin_group['Group']['id'];
		}
		
		$admin['AdminRole'] = $admin_roles;
		$admin['AdminGroup'] = $admin_groups;
		$admin['permission_group_keys'] = $permission_group_keys;
		return $admin;
	}
	
	// arg array @admin - Current admin user record
	// arg mixed @project - can be project_id or an array of project record 
	public function can_access_project($admin, $project) {
		// Admin can access anything
		if ($admin['AdminRole']['admin'] == true) {
			return true;
		}
		
		if (!empty($project) && !is_array($project)) {
			$project_id = $project;
			App::import('Model', 'Project');
			$this->Project = new Project;
			$project = $this->Project->find('first', array(
				'fields' => array(
					'Project.id',
					'Project.group_id',
				),
				'conditions' => array(
					'Project.id' => $project_id
				),
				'contain' => array(
					'ProjectAdmin'
				)
			));
		}
		
		if (empty($project)) {
			return false;
		}
		
		// group checks should be done first; even if the manager is assigned to the project
		if (!in_array($project['Project']['group_id'], $admin['AdminGroup'])) {
			return false;
		}

		// override for PMs
		if ($admin['AdminRole']['projects'] == true) {
			return true;
		}
		
		// check if the current logged-in admin is assigned to this project?
		if (isset($project['ProjectAdmin']) && !empty($project['ProjectAdmin'])) {
			$project_admins = array();
			foreach ($project['ProjectAdmin'] as $project_admin) {
				$project_admins[] = $project_admin['admin_id'];
			}
			
			if (in_array($admin['Admin']['id'], $project_admins)) {
				return true;
			}
		}
		
		return false;
	}
	
}
