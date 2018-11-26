<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 11/20/18
 * Time: 4:48 PM
 */

class Brizy_Editor_Forms_Integration extends Brizy_Admin_Serializable {

	/**
	 * @var string
	 */
	protected $id;

	/**
	 * @var array
	 */
	protected $accounts;

	/**
	 * @var array
	 */
	protected $fields;

	/**
	 * @var array
	 */
	protected $groups;


	/**
	 * @var
	 */
	protected $usedAccount;

	/**
	 * @var
	 */
	protected $fieldsMap;

	/**
	 * @var
	 */
	protected $usedGroup;


	public function __construct( $id ) {
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function serialize() {
		return serialize( $this->jsonSerialize() );
	}

	/**
	 * @return array|mixed
	 */
	public function jsonSerialize() {
		$get_object_vars = array(
			'id'          => $this->getId(),
			'accounts'    => $this->getAccounts(),
			'fields'      => $this->getFields(),
			'groups'      => $this->getGroups(),
			'usedAccount' => $this->getUsedAccount(),
			'usedGroup'   => $this->getUsedGroup(),
			'fieldsMap'   => $this->getFieldsMap(),
		);

		return $get_object_vars;
	}

	/**
	 * @return array|mixed
	 */
	public function convertToOptionValue() {
		return $this->jsonSerialize();
	}

	/**
	 * @param $data
	 *
	 * @throws Exception
	 */
	static public function createFromSerializedData( $data ) {
		throw new Exception( 'Not implemented' );
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return array
	 */
	public function getAccounts() {
		return $this->accounts;
	}

	/**
	 * @param array $accounts
	 *
	 * @return Brizy_Editor_Forms_Integration
	 */
	public function setAccounts( $accounts ) {
		$this->accounts = $accounts;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getFields() {
		return $this->fields;
	}

	/**
	 * @param array $fields
	 *
	 * @return Brizy_Editor_Forms_Integration
	 */
	public function setFields( $fields ) {
		$this->fields = $fields;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getGroups() {
		return $this->groups;
	}

	/**
	 * @param array $groups
	 *
	 * @return Brizy_Editor_Forms_Integration
	 */
	public function setGroups( $groups ) {
		$this->groups = $groups;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getUsedAccount() {
		return $this->usedAccount;
	}

	/**
	 * @param mixed $usedAccount
	 *
	 * @return Brizy_Editor_Forms_Integration
	 */
	public function setUsedAccount( $usedAccount ) {
		$this->usedAccount = $usedAccount;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getUsedGroup() {
		return $this->usedGroup;
	}

	/**
	 * @param mixed $usedGroup
	 *
	 * @return Brizy_Editor_Forms_Integration
	 */
	public function setUsedGroup( $usedGroup ) {
		$this->usedGroup = $usedGroup;

		return $this;
	}
}