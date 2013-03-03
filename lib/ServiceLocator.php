<?php
/**
 * This file is a part of Xen Orchestra Web.
 *
 * Xen Orchestra Web is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * Xen Orchestra Web is distributed in the hope that it will be
 * useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xen Orchestra Web. If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * @author Julien Fontanet <julien.fontanet@vates.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0-standalone.html GNU AGPLv3
 *
 * @package Xen Orchestra Web
 */

/**
 * Service locator.
 */
final class ServiceLocator extends Base
{
	function __construct()
	{
		parent::__construct();
	}

	function get($id, $default = 'throws an exception')
	{
		if (isset($this->_entries[$id])
		    || array_key_exists($id, $this->_entries))
		{
			return $this->_entries[$id];
		}

		$tmp = str_replace(array('_', '.'), array('', '_'), $id);

		if (method_exists($this, '_get_'.$tmp))
		{
			return $this->{'_get_'.$tmp}();
		}

		if (method_exists($this, '_init_'.$tmp))
		{
			$value = $this->{'_init_'.$tmp}();
			$this->set($id, $value);
			return $value;
		}

		// Last chance: maybe its a class name.
		if (class_exists($id))
		{
			return new $id;
		}

		// Nothing found.
		if (func_num_args() < 2)
		{
			throw new Exception('no such entry ('.$path.')');
		}

		return $default;
	}

	function set($id, $value)
	{
		$this->_entries[$id] = $value;
	}

	private $_entries = array();

	////////////////////////////////////////œ

	private function _init_application()
	{
		return new Application($this);
	}

	private function _init_controller_admin()
	{
		return new Controller\Admin($this);
	}

	private function _init_controller_home()
	{
		return new Controller\Home($this);
	}

	private function _init_controller_pools()
	{
		return new Controller\Pools($this);
	}

	private function _init_controller_servers()
	{
		return new Controller\Servers($this);
	}

	private function _init_controller_vms()
	{
		return new Controller\VMs($this);
	}

	private function _init_errorLogger()
	{
		return new ErrorLogger($this->get('logger'));
	}

	private function _init_logger()
	{
		$logger = new \Monolog\Logger('main');

		$config = $this->get('config');
		if ($email = $config->get('log.email', false))
		{
			$logger->pushHandler(
				new \Monolog\Handler\FingersCrossedHandler(
					new \Monolog\Handler\NativeMailerHandler(
						$email,
						'[XO Web]',
						'no-reply@vates.fr',
						\Monolog\Logger::DEBUG
					),
					\Monolog\Logger::WARNING
				)
			);
		}
		if ($file = $config->get('log.file', false))
		{
			$logger->pushHandler(
				new \Monolog\Handler\StreamHandler($file)
			);
		}

		return $logger;
	}

	private function _init_routes()
	{
		$base = $this->get('config')['base_path'];

		return new \Switchman\Collection(
			/* builders */ array(
				'default' => array(
					'class'   => '\Switchman\Builder\Simple',
					'options' => array(
						'pattern'  => $base.'/:controller/:action',
						'defaults' => array(
							'action' => 'index',
						),
					),
				),
			),
			/* matchers */ array(
				'default' => array(
					'class'   => '\Switchman\Matcher\Regex',
					'options' => array(
						'patterns' => array(
							'path' => ',^/(?:(?<controller>[a-z]+)(?:/(?<action>[a-z]+))?/?)?$,i',
						),
						'defaults' => array(
							'controller' => 'home',
							'action'     => 'index'
						),
					),
				),
			)
		);
	}

	private function _init_template_manager()
	{
		$config = $this->get('config');

		$tm = new Gallic_Template_Manager(
			__DIR__.'/../views',
			$config->get('templates.ttl'),
			$config->get('templates.cache', null)
		);

		$tm->defaultFilters += array(
			'count' => 'count',
			'json'  => 'json_encode',
		);

		$tu = new TemplateUtils($this);
		$tm->defaultFunctions += array(
			'generateSelectOptions' => array($tu, 'generateSelectOptions'),
			'url'                   => array($tu, 'url'),
		);

		$tm->defaultVariables += array(
			'base_path' => $this->get('config')['base_path'],
			'user'      => $this->get('application')->getCurrentUser(),
		);

		return $tm;
	}

	private function _init_xo()
	{
		$xo = new XO($this->get('config')['xo.url']);

		if (isset($_SESSION['user']['token']))
		{
			$xo->session->signInWithToken($_SESSION['user']['token']);
		}

		return $xo;
	}
}