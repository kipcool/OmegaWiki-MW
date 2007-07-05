<?php

require_once("Wikidata.php");

interface DatabaseExpression {
	public function toExpression();
}

class TableColumn implements DatabaseExpression {
	public $table;
	public $identifier;
	
	public function __construct($table, $identifier) {
		$this->table = $table;		
		$this->identifier = $identifier;
	}
	
	public function getIdentifier() {
		return $this->identifier;
	}
	
	public function qualifiedName() {
		return $this->table->getIdentifier() . '.' . $this->identifier;
	}
	
	public function toExpression() {
		return $this->qualifiedName();
	}
}

class Table {
	public $identifier;
	public $isVersioned;
	public $keyFields;
	public $columns;
	
	public function __construct($identifier, $isVersioned, $keyFields) {
		# Without dataset prefix!
		$this->identifier = $identifier;
		$this->isVersioned = $isVersioned;
		$this->keyFields = $keyFields;
		$this->columns = array();
	}

	public function getIdentifier() {
		$dc = wdGetDataSetContext();
		return "{$dc}_" . $this->identifier;
	}	
	
	protected function createColumn($identifier) {
		$result = new TableColumn($this, $identifier);
		$this->columns[] = $result;
		
		return $result;
	}
}

class VersionedTable extends Table {
	public $addTransactionId;
	public $removeTransactionId;
	
	public function __construct($identifier, $keyFields) {
		parent::__construct($identifier, true, $keyFields);
		
		$this->addTransactionId = $this->createColumn("add_transaction_id");
		$this->removeTransactionId = $this->createColumn("remove_transaction_id");
	}
}

class BootstrappedDefinedMeaningsTable extends Table {
	public $name;
	public $definedMeaningId;
	
	public function __construct($identifier) {
		parent::__construct($identifier, false, array("name"));
		
		$this->name = $this->createColumn("name");
		$this->definedMeaningId = $this->createColumn("defined_meaning_id");
	}
}

class DefinedMeaningTable extends VersionedTable {
	public $definedMeaningId;
	public $expressionId;
	
	public function __construct($identifier) {
		parent::__construct($identifier, array("defined_meaning_id"));
		
		$this->definedMeaningId = $this->createColumn("defined_meaning_id");
		$this->expressionId = $this->createColumn("expression_id");
	}
}

class ExpressionTable extends VersionedTable {
	public $expressionId;
	public $spelling;	
	public $languageId;
	
	public function __construct($name) {
		parent::__construct($name, array("expression_id"));
		
		$this->expressionId = $this->createColumn("expression_id");
		$this->spelling = $this->createColumn("spelling");
		$this->languageId = $this->createColumn("language_id");
	}
}

global
	$tables, 

	$alternativeDefinitionsTable,
	$bootstrappedDefinedMeaningsTable, 
	$classAttributesTable,
	$classMembershipsTable, 
	$collectionMembershipsTable,
	$definedMeaningTable, 
	$expressionTable,
	$meaningRelationsTable, 
	$optionAttributeOptionsTable, 
	$optionAttributeValuesTable, 
	$syntransTable, 
	$textAttributeValuesTable, 
	$translatedContentAttributeValuesTable, 
	$translatedContentTable, 
	$transactionsTable,
	$urlAttributeValuesTable;

$dc=wdGetDataSetContext();
$alternativeDefinitionsTable = new Table("alt_meaningtexts", true, array('meaning_mid', 'meaning_text_tcid'));
$bootstrappedDefinedMeaningsTable = new BootstrappedDefinedMeaningsTable("bootstrapped_defined_meanings");
$classAttributesTable = new Table("class_attributes", true, array('object_id'));
$classMembershipsTable = new Table("class_membership", true, array('class_membership_id'));
$collectionMembershipsTable = new Table("collection_contents", true, array('collection_id', 'member_mid'));
$definedMeaningTable = new DefinedMeaningTable("defined_meaning");
$expressionTable = new ExpressionTable("expression_ns");
$meaningRelationsTable = new Table("meaning_relations", true, array('relation_id'));
$syntransTable = new Table("syntrans", true, array('syntrans_sid'));
$textAttributeValuesTable = new Table("text_attribute_values", true, array('value_id'));
$transactionsTable = new Table("transactions", false, array('transaction_id'));
$translatedContentAttributeValuesTable = new Table("translated_content_attribute_values", true, array('value_id'));
$translatedContentTable = new Table("translated_content", true, array('translated_content_id', 'language_id'));
$optionAttributeOptionsTable = new Table("option_attribute_options", true, array('attribute_id', 'option_mid'));
$optionAttributeValuesTable = new Table("option_attribute_values", true, array('value_id'));
$urlAttributeValuesTable = new Table("url_attribute_values", true, array('value_id'));

function select($expressions, $tables, $restrictions) {
	$result = "SELECT " . $expressions[0]->toExpression();
	
	for ($i = 1; $i < count($expressions); $i++)
		$result .= ", " . $expressions[$i]->toExpression();
		
	if (count($tables) > 0) {
		$result .= " FROM " . $tables[0]->getIdentifier();
		
		for ($i = 1; $i < count($tables); $i++)
			$result .= ", " . $tables[$i]->getIdentifier();
	}
	
	if (count($restrictions) > 0) {
		$result .= " WHERE (" . $restrictions[0] . ")";
		
		for ($i = 1; $i < count($restrictions); $i++)
			$result .= " AND (" . $restrictions[$i] . ")";
	}
	
	return $result;
}

function selectLatest($expressions, $tables, $restrictions) {
	foreach($tables as $table)
		if ($table->isVersioned)
			$restrictions[] = $table->removeTransactionId->toExpression() . " IS NULL";
	
	return select($expressions, $tables, $restrictions);
}

function equals(DatabaseExpression $expression1, DatabaseExpression $expression2) {
	return '(' . $expression1->toExpression() . ') = (' . $expression2->toExpression() . ')';
}

?>