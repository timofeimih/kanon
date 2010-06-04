<?php
class modelQueryBuilder{
	/**
	 *
	 * @var modelCollection
	 */
	protected $_storageSource = null;
	protected $_joinedTables = array(); // all tables joined by user
	protected $_selectedTables = array(); // all tables in select
	protected $_selected = array();
	protected $_limitFrom = 0;
	protected $_limit = null;

	protected $_joinOptions = array();
	protected $_joinType = array();
	protected $_joinOn = array();
	protected $_join = array();
	protected $_where = array();
	protected $_having = array();
	protected $_order = array();
	protected $_group = array();
	protected $_filters = array();
	public function addFilter($filter){
		$this->_filters[] = $filter;
		return $this;
	}
	/**
	 * @return modelStorage
	 */
	public function getStorage(){
		if ($this->_storageSource === null) return false;
		return  $this->_storageSource->getStorage();
	}
	public function e($unescapedString){
		return $this->getStorage()->quote($unescapedString);
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &select(){
		$args = func_get_args();
		if (!count($args)) return $this;
		foreach ($args as $arg){
			if ($arg instanceof modelAggregation){
				$fields = $arg->getArguments();
				foreach ($fields as $field){
					$a["$field"] = $field;
				}
				$field = $arg;
				$this->_selected[] = $arg;
			}else{
				$table = null;
				$field = null;
				if ($arg instanceof modelCollection){
					$table = $arg;
				}
				if ($arg instanceof modelField){
					$table = $arg->getCollection();
					$field = $arg;
				}

				if ($this->_storageSource === null) $this->_storageSource = $table;
				$a = array();
				if ($arg instanceof modelCollection){
					foreach ($table->getFieldNames() as $fieldName){
						$field = $table[$fieldName];
						$a["$field"] = $field;
					}
				}
				if ($arg instanceof modelField){
					$a["$field"] = $field;
				}
				$this->_selected[] = array($table, $a);
				$this->_selectedTables[$table->getUniqueId()] = $table;
				$this->_joinedTables[$table->getUniqueId()] = $table;
			}
		}
		//var_dump($this->_selected);
		return $this;
	}
	protected function _constructJoins(){
		$this->getStorage()->registerForeignKeys();
		$this->_join = array(); // reset joins
		$sourceTable = $this->_storageSource;
		$sourceTableUid = $sourceTable->getUniqueId();
		$joined = array();
		$joined[$sourceTable->getUniqueId()] = true;
		foreach ($this->_joinedTables as $tableUid => $table2){
			if ($sourceTableUid !== $tableUid){ //
				// Trying to join table
				$joinType = 'INNER';
				$joinOn = '';
				if (isset($this->_joinType[$table2->getUniqueId()])){
					$joinType = $this->_joinType[$table2->getUniqueId()];
				}
				if (isset($this->_joinOn[$table2->getUniqueId()])){
					$joinOn = $this->_joinOn[$table2->getUniqueId()];
				}else{
					$joinOn = $table2->getJoinOn($sourceTable);
				}
				$joins = modelStorage::getIndirectTablesJoins($sourceTable, $table2, $joinType, $joinOn);
				if ($joins !== false){
					foreach ($joins as $uid => $joinString){
						if (!isset($joined[$uid])){
							$this->_join[] = $joinString;
							$joined[$uid] = true;
						}
					}
				}
			}
		}
		//var_dump($this->_join);
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &join($table2, $joinType = 'INNER', $on = ''){
		//echo $joinType;
		$this->_joinType[$table2->getUniqueId()] = $joinType;
		if (strlen($on)){
			$this->_joinOn[$table2->getUniqueId()] = $on;
		}
		$this->_joinedTables[$table2->getUniqueId()] = $table2;
		return $this;
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &autoJoin($table2){
		$joinType = isset($this->_joinType[$table2->getUniqueId()])?$this->_joinType[$table2->getUniqueId()]:'INNER';
		$this->_joinType[$table2->getUniqueId()] = $joinType;
		$this->_joinedTables[$table2->getUniqueId()] = $table2;
		return $this;
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &leftJoin($table2, $on = ''){
		return $this->join($table2, 'LEFT', $on);
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &innerJoin($table2, $on = ''){
		return $this->join($table2, 'INNER', $on);
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &limit(){
		$args = func_get_args();
		switch (count($args)){
			case 1:
				$this->_limit = $args[0];
				$this->_limitFrom = 0;
				break;
			case 2:
				$this->_limit = $args[1];
				$this->_limitFrom = $args[0];
				break;
			default:
				$this->_limit = null;
				$this->_limitFrom = 0;
		}
		return $this;
	}
	protected function _joinCondition($condition){
		if ($condition instanceof modelExpression){
			$left = $condition->getLeft();
			if ($left instanceof modelField){
				$this->autoJoin($left->getCollection());
			}
			$right = $condition->getRight();
			if ($right instanceof modelField){
				$this->autoJoin($right->getCollection());
			}
		}
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &where(){
		$conditions = func_num_args()?func_get_args():array();
		foreach ($conditions as $condition){
			$this->_where[] = $condition;
			$this->_joinCondition($condition);
		}
		return $this;
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &having($condition){
		$this->_having[] = $condition;
		$this->_joinCondition($condition);
		return $this;
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &asc($field){
		$this->_order[] = $field.' ASC';
		if ($field instanceof modelField){
			$this->autoJoin($field->getCollection());
		}
		return $this;
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &desc($field){
		$this->_order[] = $field.' DESC';
		if ($field instanceof modelField){
			$this->autoJoin($field->getCollection());
		}
		return $this;
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &orderBy($orderString){
		$this->_order[] = $orderString;
		return $this;
	}
	/**
	 * @return modelQueryBuilder
	 */
	public function &groupBy($groupString){
		$this->_group[] = $groupString;
		return $this;
	}
	protected function getWhatSql(){
		$wa = array();
		foreach ($this->_selected as $sa){
			if (is_array($sa)){
				list($table, $fields) = $sa;
				foreach ($fields as $fid => $field){
					$wa[] = $field." as ".$field->getUniqueId();
				}
			}else{
				$wa[] = "$sa";
			}
		}
		return implode(", ", $wa);
	}
	protected function getJoinSql(){
		$this->_constructJoins();
		return implode("", $this->_join);
	}
	protected function getFromSql(){
		reset($this->_selected);
		$sa = current($this->_selected);
		list($table, $fields) = $sa;
		return " FROM ".$table->getTableName()." as ".$table;
	}
	protected function getOrderSql(){
		if (count($this->_order)){
			return " ORDER BY ".implode(", ", $this->_order);
		}
		return '';
	}
	protected function getWhereSql(){
		if (count($this->_where)){
			$wa = array();
			foreach ($this->_where as $k => $condition){
				if (strval($condition) != ''){
					$wa[] = $condition;
				}
			}
			return " WHERE ".implode(" AND ", $wa);
		}
		return '';
	}
	protected function getHavingSql(){
		if (count($this->_having)){
			return " HAVING ".implode(" AND ", $this->_having);
		}
		return '';
	}
	protected function getGroupBySql(){
		if (count($this->_group)){
			return " GROUP BY ".implode(", ", $this->_group);
		}
		return '';
	}
	protected function getLimitSql(){
		if ($this->_limitFrom){
			if ($this->_limit){
				return " LIMIT $this->_limitFrom, $this->_limit";
			}else{
				return "";//,18446744073709551615;
			}
		}else{
			if ($this->_limit){
				return " LIMIT $this->_limit";
			}else{
				return "";//,18446744073709551615;
			}
		}
	}
	protected function applyFilters(){
		foreach ($this->_joinedTables as $tableUid => $table){
			$filters = $table->getFilters();
			foreach ($filters as $filter){
				$this->where($filter);
			}
		}
	}
	public function &getSql(){
		$this->getStorage()->registerForeignKeys();
		$this->applyFilters();
		$sql = "SELECT ".$this->getWhatSql()
		.$this->getFromSql()
		// join
		.$this->getJoinSql()
		.$this->getWhereSql()
		.$this->getGroupBySql()
		.$this->getHavingSql()
		.$this->getOrderSql()
		.$this->getLimitSql();
		//echo '<b>'.$sql.'</b><br />';
		return $sql;
	}
	public function &getCountSql(){
		$this->getStorage()->registerForeignKeys();
		$this->applyFilters();
		$sql = "SELECT COUNT(*)"
		.$this->getFromSql()
		// join
		.$this->getJoinSql()
		.$this->getWhereSql()
		.$this->getGroupBySql()
		.$this->getHavingSql()
		.$this->getOrderSql()
		.$this->getLimitSql();
		return $sql;
	}
	public function __toString(){
		return $this->getSql();
	}

}