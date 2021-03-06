<?php
/**
 * Displays a {@link SS_List} in a grid format.
 * 
 * GridField is a field that takes an SS_List and displays it in an table with rows 
 * and columns. It reminds of the old TableFields but works with SS_List types 
 * and only loads the necessary rows from the list.
 * 
 * The minimum configuration is to pass in name and title of the field and a 
 * SS_List.
 * 
 * <code>
 * $gridField = new GridField('ExampleGrid', 'Example grid', new DataList('Page'));
 * </code>
 * 
 * @see SS_List
 * 
 * @package sapphire
 * @subpackage fields-relational
 */
class GridField extends FormField {
	
	/**
	 *
	 * @var array
	 */
	public static $allowed_actions = array(
		'gridFieldAlterAction'
	);
	
	/** @var SS_List - the datasource */
	protected $list = null;

	/** @var string - the classname of the DataObject that the GridField will display. Defaults to the value of $this->list->dataClass */
	protected $modelClassName = '';
	
	/** @var array */
	public $fieldCasting = array();

	/** @var array */
	public $fieldFormatting = array();

	/** @var GridState - the current state of the GridField */
	protected $state = null;
	
	/**
	 *
	 * @var GridFieldConfig
	 */
	protected $config = null;
	
	/**
	 * The components list 
	 */
	protected $components = array();
	
	/**
	 * This is the columns that will be visible
	 *
	 * @var array
	 */
	protected $displayFields = array();
	
	/**
	 * Internal dispatcher for column handlers.
	 * Keys are column names and values are GridField_ColumnProvider objects
	 * 
	 * @var array
	 */
	protected $columnDispatch = null;

	/**
	 * Creates a new GridField field
	 *
	 * @param string $name
	 * @param string $title
	 * @param SS_List $dataList
	 * @param GridFieldConfig $config
	 */
	public function __construct($name, $title = null, SS_List $dataList = null, GridFieldConfig $config = null) {
		parent::__construct($name, $title, null);
		
		FormField::__construct($name);

		if($dataList) {
			$this->setList($dataList);
		}
		
		if(!$config) {
			$this->config = GridFieldConfig_Base::create();
		} else {
			$this->config = $config;
		}
		
		$this->config->addComponent(new GridState_Component());
		$this->setComponents($this->config);
		$this->state = new GridState($this);
		
		
		$this->addExtraClass('ss-gridfield');
		$this->requireDefaultCSS();
		
	}
	
	/**
	 * Set the modelClass (dataobject) that this field will get it column headers from.
	 * If no $displayFields has been set, the displayfields will be fetched from
	 * this modelclass $summary_fields
	 * 
	 * @param string $modelClassName
	 * @see GridField::getDisplayFields()
	 */
	public function setModelClass($modelClassName) {
		$this->modelClassName = $modelClassName;
		return $this;
	}
	
	/**
	 * Returns a dataclass that is a DataObject type that this GridField should look like.
	 * 
	 * @throws Exception
	 * @return string
	 */
	public function getModelClass() {
		if ($this->modelClassName) return $this->modelClassName;
		if ($this->list && method_exists($this->list, 'dataClass')) {
			$class = $this->list->dataClass();
			if($class) return $class;
		}

		throw new LogicException('GridField doesn\'t have a modelClassName, so it doesn\'t know the columns of this grid.');
	}
	
	/**
	 * Set which GridFieldComponent's that this GridFields contain by using a GridFieldConfig
	 *
	 * @param GridFieldConfig $config
	 * @see GridFieldComponent
	 */
	protected function setComponents(GridFieldConfig $config) {
		$this->components = $config->getComponents();
		return $this;
	}
	
	/**
	 * Require the default css styling
	 */
	protected function requireDefaultCSS() {
		Requirements::css('sapphire/css/GridField.css');
	}

	/**
	 * Get the DisplayFields
	 * 
	 * @return array
	 * @see GridField::setDisplayFields
	 */
	public function getDisplayFields() {
		if(!$this->displayFields) {
			return singleton($this->getModelClass())->summaryFields();
		}
		return $this->displayFields;
	}
	
	/**
	 * Get the GridFieldConfig
	 *
	 * @return GridFieldConfig
	 */
	public function getConfig() {
		return $this->config;
	}
	
	/**
	 * Override the default behaviour of showing the models summaryFields with
	 * these fields instead
	 * Example: array( 'Name' => 'Members name', 'Email' => 'Email address')
	 *
	 * @param array $fields 
	 */
	public function setDisplayFields($fields) {
		if(!is_array($fields)) {
			throw new InvalidArgumentException('Arguments passed to GridField::setDisplayFields() must be an array');
		}
		$this->displayFields = $fields;
		return $this;
	}

	/**
	 * Specify castings with fieldname as the key, and the desired casting as value.
	 * Example: array("MyCustomDate"=>"Date","MyShortText"=>"Text->FirstSentence")
	 *
	 * @param array $casting
	 * @todo refactor this into GridFieldComponent
	 */
	public function setFieldCasting($casting) {
		$this->fieldCasting = $casting;
		return $this;
	}

	/**
	 * Specify custom formatting for fields, e.g. to render a link instead of pure text.
	 * Caution: Make sure to escape special php-characters like in a normal php-statement.
	 * Example:	"myFieldName" => '<a href=\"custom-admin/$ID\">$ID</a>'
	 *
	 * @param array $casting
	 * @todo refactor this into GridFieldComponent
	 */
	public function getFieldCasting() {
		return $this->fieldCasting;
	}

	/**
	 * @param array $casting
	 * @todo refactor this into GridFieldComponent
	 */
	public function setFieldFormatting($formatting) {
		$this->fieldFormatting = $formatting;
		return $this;
	}

	/**
	 * @param array $casting
	 * @todo refactor this into GridFieldComponent
	 */
	public function getFieldFormatting() {
		return $this->fieldFormatting;
	}
	
	/**
	 * Cast a arbitrary value with the help of a castingDefintion
	 * 
	 * @param $value 
	 * @param $castingDefinition
	 * @todo refactor this into GridFieldComponent
	 */
	public function getCastedValue($value, $castingDefinition) {
		if(is_array($castingDefinition)) {
			$castingParams = $castingDefinition;
			array_shift($castingParams);
			$castingDefinition = array_shift($castingDefinition);
		} else {
			$castingParams = array();
		}
		
		if(strpos($castingDefinition,'->') === false) {
			$castingFieldType = $castingDefinition;
			$castingField = DBField::create($castingFieldType, $value);
			$value = call_user_func_array(array($castingField,'XML'),$castingParams);
		} else {
			$fieldTypeParts = explode('->', $castingDefinition);
			$castingFieldType = $fieldTypeParts[0];	
			$castingMethod = $fieldTypeParts[1];
			$castingField = DBField::create($castingFieldType, $value);
			$value = call_user_func_array(array($castingField,$castingMethod),$castingParams);
		}
		
		return $value;
	}	 

	/**
	 * Set the datasource
	 *
	 * @param SS_List $list
	 */
	public function setList(SS_List $list) {
		$this->list = $list;
		return $this;
	}

	/**
	 * Get the datasource
	 *
	 * @return SS_List
	 */
	public function getList() {
		return $this->list;
	}
	
	/**
	 * Get the current GridState_Data or the GridState
	 *
	 * @param bool $getData - flag for returning the GridState_Data or the GridState
	 * @return GridState_data|GridState
	 */
	public function getState($getData=true) {
		if($getData) {
			return $this->state->getData();
		}
		return $this->state;
	}

	/**
	 * Returns the whole gridfield rendered with all the attached components
	 *
	 * @return string
	 */
	public function FieldHolder() {
		// Get columns
		$columns = $this->getColumns();

		// Get data
		$list = $this->getList();
		foreach($this->components as $item) {
 			if($item instanceof GridField_DataManipulator) {
				$list = $item->getManipulatedData($this, $list);
			}
		}
		
		// Render headers, footers, etc
		$content = array(
			'header' => array(),
			'body' => array(),
			'footer' => array(),
			'before' => array(),
			'after' => array(),
		);

		foreach($this->components as $item) {			
			if($item instanceof GridField_HTMLProvider) {
				$fragments = $item->getHTMLFragments($this);
				foreach($fragments as $k => $v) {
					$content[$k][] = $v;
				}
			}
		}

		foreach($list as $idx => $record) {
			$record->iteratorProperties($idx, $list->count());
			$rowContent = '';
			foreach($columns as $column) {
				$colContent = $this->getColumnContent($record, $column);
				// A return value of null means this columns should be skipped altogether.
				if($colContent === null) continue;
				$colAttributes = $this->getColumnAttributes($record, $column);
				$rowContent .= $this->createTag('td', $colAttributes, $colContent);
			}
			$row = $this->createTag(
				'tr', 
				array(
					"class" => 'ss-gridfield-item ' . $record->FirstLast() . " " . $record->EvenOdd(),
					'data-id' => $record->ID
				),
				$rowContent
			);
			$content['body'][] = $row;
		}

		// Turn into the relevant parts of a table
		$head = $content['header'] ? $this->createTag('thead', array(), implode("\n", $content['header'])) : '';
		$body = $content['body'] ? $this->createTag('tbody', array('class' => 'ss-gridfield-items'), implode("\n", $content['body'])) : '';
		$foot = $content['footer'] ? $this->createTag('tfoot', array(), implode("\n", $content['footer'])) : '';

		$this->addExtraClass('ss-gridfield');
		$attrs = array_diff_key(
			$this->getAttributes(), 
			array('value' => false, 'type' => false, 'name' => false)
		);
		$tableAttrs = array(
			'id' => isset($this->id) ? $this->id : null,
			'class' => "field CompositeField {$this->extraClass()}",
			'cellpadding' => '0',
			'cellspacing' => '0'	
		);
		return
			$this->createTag('fieldset', $attrs, 
				implode("\n", $content['before']) .
				$this->createTag('table', $tableAttrs, $head."\n".$foot."\n".$body) .
				implode("\n", $content['after'])
			);
	}

	/**
	 * Get the columns of this GridField, they are provided by attached GridField_ColumnProvider
	 *
	 * @return array
	 */
	public function getColumns() {
		// Get column list
		$columns = array();
		foreach($this->components as $item) {
			if($item instanceof GridField_ColumnProvider) {
				$item->augmentColumns($this, $columns);
			}
		}
		return $columns;
	}

	/**
	 * Get the value from a column
	 *
	 * @param DataObject $record
	 * @param string $column
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public function getColumnContent($record, $column) {
		// Build the column dispatch
		if(!$this->columnDispatch) {
			$this->buildColumnDispatch();
		}
		
		if(!empty($this->columnDispatch[$column])) {
			$handler = $this->columnDispatch[$column];
			return $handler->getColumnContent($this, $record, $column);
		} else {
			throw new InvalidArgumentException("Bad column '$column'");
		}
	}

	/**
	 * Get extra columns attributes used as HTML attributes
	 *
	 * @param DataObject $record
	 * @param string $column
	 * @return array
	 * @throws LogicException
	 * @throws InvalidArgumentException
	 */
	public function getColumnAttributes($record, $column) {
		// Build the column dispatch
		if(!$this->columnDispatch) {
			$this->buildColumnDispatch();
		}
		
		if(!empty($this->columnDispatch[$column])) {
			$handler = $this->columnDispatch[$column];
			$attrs =  $handler->getColumnAttributes($this, $record, $column);
			if(is_array($attrs)) {
				return $attrs;
			}  elseif($attrs) {
				throw new LogicException("Non-array response from " . get_class($handler) . "::getColumnAttributes()");
			} else {
				return array();
			}
		} else {
			throw new InvalidArgumentException("Bad column '$column'");
		}
	}

	/**
	 * Get metadata for a column, example array('Title'=>'Email address')
	 *
	 * @param string $column
	 * @return array
	 * @throws LogicException
	 * @throws InvalidArgumentException
	 */
	public function getColumnMetadata($column) {
		// Build the column dispatch
		if(!$this->columnDispatch) {
			$this->buildColumnDispatch();
		}
		
		if(!empty($this->columnDispatch[$column])) {
			$handler = $this->columnDispatch[$column];
			$metadata = $handler->getColumnMetadata($this, $column);
			if(is_array($metadata)) {
				return $metadata;
			} elseif($metadata) {
				throw new LogicException("Non-array response from " . get_class($handler) . "::getColumnMetadata()");
			}
		}
		throw new InvalidArgumentException("Bad column '$column'");
	}

	/**
	 * Return how many columns the grid will have
	 *
	 * @return int
	 */
	public function getColumnCount() {
		// Build the column dispatch
		if(!$this->columnDispatch) $this->buildColumnDispatch();
		return count($this->columnDispatch);	
	}

	/**
	 * Build an columnDispatch that maps a GridField_ColumnProvider to a column
	 * for reference later
	 * 
	 */
	protected function buildColumnDispatch() {
		$this->columnDispatch = array();
		foreach($this->components as $item) {
			if($item instanceof GridField_ColumnProvider) {
				$columns = $item->getColumnsHandled($this);
				foreach($columns as $column) {
					$this->columnDispatch[$column] = $item;
				}
			}
		}			
	}
	
	/**
	 * This is the action that gets executed when a GridField_AlterAction gets clicked.
	 *
	 * @param array $data
	 * @return string 
	 */
	public function gridFieldAlterAction($data, $form, SS_HTTPRequest $request) {
		$id = $data['StateID'];
		$stateChange = Session::get($id);
		$gridName = $stateChange['grid'];
		$grid = $form->Fields()->dataFieldByName($gridName);
		
		$state = $grid->getState(false);
		if(isset($data['GridState'])) $state->setValue($data['GridState']);
		
		$actionName = $stateChange['actionName'];
		$args = isset($stateChange['args']) ? $stateChange['args'] : array();
		$html = $grid->handleAction($actionName, $args, $data);
		
		if($html) {
			return $html;
		}
		
		switch($request->getHeader('X-Get-Fragment')) {
			case 'CurrentField':
				return $grid->FieldHolder();
				break;

			case 'CurrentForm':
				return $form->forTemplate();
				break;

			default:
				return $form->forTemplate();
				break;
		}
	}

	/**
	 * Pass an action on the first GridField_ActionProvider that matches the $actionName
	 *
	 * @param string $actionName
	 * @param mixed $args
	 * @param arrray $data - send data from a form
	 * @return type
	 * @throws InvalidArgumentException
	 */
	public function handleAction($actionName, $args, $data) {
		$actionName = strtolower($actionName);
		foreach($this->components as $component) {
			if(!($component instanceof GridField_ActionProvider)) {
				continue;
			}
			
			if(in_array($actionName, array_map('strtolower', $component->getActions($this)))) {
				return $component->handleAction($this, $actionName, $args, $data);
			}
		}
		throw new InvalidArgumentException("Can't handle action '$actionName'");
	}
	
	/**
	 * Custom request handler that will check component handlers before proceeding to the default implementation.
	 * 
	 * @todo There is too much code copied from RequestHandler here.
	 */
	function handleRequest(SS_HTTPRequest $request, DataModel $model) {
		if($this->brokenOnConstruct) {
			user_error("parent::__construct() needs to be called on {$handlerClass}::__construct()", E_USER_WARNING);
		}

		$this->request = $request;
		$this->setModel($model);
		
		foreach($this->components as $component) {
			if(!($component instanceof GridField_URLHandler)) {
				continue;
			}
			
			$urlHandlers = $component->getURLHandlers($this);
			
			if($urlHandlers) foreach($urlHandlers as $rule => $action) {
				if($params = $request->match($rule, true)) {
					// Actions can reference URL parameters, eg, '$Action/$ID/$OtherID' => '$Action',
					if($action[0] == '$') $action = $params[substr($action,1)];
					if(!method_exists($component, 'checkAccessAction') || $component->checkAccessAction($action)) {
						if(!$action) {
							$action = "index";
						} else if(!is_string($action)) {
							throw new LogicException("Non-string method name: " . var_export($action, true));
						}

						try {
							$result = $component->$action($this, $request);
						} catch(SS_HTTPResponse_Exception $responseException) {
							$result = $responseException->getResponse();
						}

						if($result instanceof SS_HTTPResponse && $result->isError()) {
							return $result;
						}

						if($this !== $result && !$request->isEmptyPattern($rule) && is_object($result) && $result instanceof RequestHandler) {
							$returnValue = $result->handleRequest($request, $model);

							if(is_array($returnValue)) {
								throw new LogicException("GridField_URLHandler handlers can't return arrays");
							}

							return $returnValue;

						// If we return some other data, and all the URL is parsed, then return that
						} else if($request->allParsed()) {
							return $result;

						// But if we have more content on the URL and we don't know what to do with it, return an error.
						} else {
							return $this->httpError(404, "I can't handle sub-URLs of a " . get_class($result) . " object.");
						}
					}
				}
			}
		}
		
		return parent::handleRequest($request, $model);
	}
}


/**
 * This class is the base class when you want to have an action that alters the state of the gridfield
 * 
 * @package sapphire
 * @subpackage forms
 * 
 */
class GridField_Action extends FormAction {

	/**
	 *
	 * @var GridField
	 */
	protected $gridField;
	
	/**
	 *
	 * @var string
	 */
	protected $buttonLabel;
	
	/**
	 *
	 * @var array 
	 */
	protected $stateValues;
	
	/**
	 *
	 * @var array
	 */
	//protected $stateFields = array();
	
	protected $actionName;
	protected $args = array();

	/**
	 *
	 * @param GridField $gridField
	 * @param type $name
	 * @param type $label
	 * @param type $actionName
	 * @param type $args 
	 */
	public function __construct(GridField $gridField, $name, $label, $actionName, $args) {
		$this->gridField = $gridField;
		$this->buttonLabel = $label;
		$this->actionName = $actionName;
		$this->args = $args;
		parent::__construct($name);
	}

	/**
	 * urlencode encodes less characters in percent form than we need - we need everything that isn't a \w
	 * 
	 * @param string $val
	 */
	public function nameEncode($val) {
		return preg_replace_callback('/[^\w]/', array($this, '_nameEncode'), $val);
	}

	/**
	 * The callback for nameEncode
	 * 
	 * @param string $val
	 */
	public function _nameEncode($match) {
		return '%'.dechex(ord($match[0]));
	}

	/**
	 * Default method used by Templates to render the form
	 *
	 * @return string HTML tag
	 */
	public function Field() {
		// Store state in session, and pass ID to client side
		$state = array(
			'grid' => $this->getNameFromParent(),
			'actionName' => $this->actionName,
			'args' => $this->args,
		);
		
		$id = preg_replace('/[^\w]+/', '_', uniqid('', true));
		Session::set($id, $state);
		
		$actionData['StateID'] = $id;
		
		// And generate field
		$attributes = array(
			'class' => 'action' . ($this->extraClass() ? $this->extraClass() : ''),
			'id' => $this->id(),
			'type' => 'submit',
			// Note:  This field needs to be less than 65 chars, otherwise Suhosin security patch 
			// will strip it from the requests 
			'name' => 'action_gridFieldAlterAction'. '?' . http_build_query($actionData),
			'tabindex' => $this->getTabIndex(),
		);

		if($this->isReadonly()) {
			$attributes['disabled'] = 'disabled';
			$attributes['class'] = $attributes['class'] . ' disabled';
		}
		
		return $this->createTag('button', $attributes, $this->buttonLabel);
	}

	/**
	 * Calculate the name of the gridfield relative to the Form
	 *
	 * @param GridField $base
	 * @return string
	 */
	protected function getNameFromParent() {
		$base = $this->gridField;
		$name = array();
		do {
			array_unshift($name, $base->getName());
			$base = $base->getForm();
		} while ($base && !($base instanceof Form));
		return implode('.', $name);
	}
}
