<?php

require_once("attribute.php");
require_once("tuple.php");
require_once("relation.php");

function parityClass($value) {
	if ($value % 2 == 0)
		return "even";
	else
		return "odd";
}

/* Functions to create a hierarchical table header
 * using rowspan and colspan for <th> elements
 */

function mergeHeadingBlocks($lhs, $rhs) {
	$result = $lhs;
	
	for ($i = 0; $i < count($rhs); $i++) {
		if ($i < count($result)) 
			$result[$i] = array_merge($result[$i], $rhs[$i]);
		else
			$result[$i] = $rhs[$i]; 
	}

	return $result;
}

function getHeadingBlock($heading) {
	$block = array();
	$width = 0;
	$height = 0;
	
	foreach($heading->attributes as $attribute) {
		$type = $attribute->type;
		
		if (is_a($type, TupleType)) {
			list($childBlock, $childWidth, $childHeight) = getHeadingBlock($type->getHeading());
			array_unshift($childBlock, array(array($attribute, $childWidth, $childHeight + 1)));
			$width += $childWidth;
			$height = max($height, $childHeight + 1);
			$block = mergeHeadingBlocks($block, $childBlock);
		}
		else { 
			$block = mergeHeadingBlocks($block, array(array(array($attribute, 1, 1))));
			$height = max($height, 1);
			$width++;
		}
	}
	
	return array($block, $width, $height);
}

function getHeadingAsTableHeaderRows($heading) {
	list($headingBlock, $width, $height) = getHeadingBlock($heading);
	
	$result = array();
	
	for ($i = 0; $i < $height; $i++) {
		$row = '';
		
		foreach($headingBlock[$i] as $block) {
			list($attribute, $blockWidth, $blockHeight) = $block;
			
			if (!is_a($attribute->type, TupleType))
				$class = ' class="'. $attribute->type .'"';	
			else		
				$class = '';
				
			$row .= '<th' . $class .' colspan="'. $blockWidth . 
							'" rowspan="'. ($height - $blockHeight - $i + 1) . '">'. $attribute->name . '</th>';
		}
		
		$result[] = $row;	
	}
	
	return $result;
}

function getTupleAsTableCells($heading, $tuple, &$startColumn = 0) {
	$result = '';
	
	foreach($heading->attributes as $attribute) {
		$type = $attribute->type;
		$value = $tuple->getAttributeValue($attribute);
		
		if (is_a($type, TupleType)) {
			$result .= getTupleAsTableCells($type->getHeading(), $value, $startColumn);	
		}
		else {
			$result .= '<td class="'. $type .' column-'. parityClass($startColumn) . '">'. convertToHTML($value, $type) . '</td>';
			$startColumn++;
		}
	}
	
	return $result;
}

function getTupleAsEditTableCells($tuple, $updateId, $updatableHeading, &$startColumn = 0) {
	$result = '';
	
	foreach($tuple->getHeading()->attributes as $attribute) {
		$type = $attribute->type;
		$value = $tuple->getAttributeValue($attribute);
			
		if (is_a($type, TupleType))			
			$result .= getTupleAsEditTableCells($value, $updateId . $attribute->id . '-', $updatableHeading, $startColumn); 
		else {	
			if (in_array($attribute, $updatableHeading->attributes)) {
				$inputField = getInputFieldForType($updateId . $attribute->id, $type, $value);
				$result .= '<td class="'. $type .' column-'. parityClass($startColumn) . '">'. $inputField . '</td>';
			}
			else
				$result .= '<td class="'. $type .' column-'. parityClass($startColumn) . '">'. convertToHTML($value, $type) . '</td>';
				
			$startColumn++;
		}
	}
	
	return $result;
}

function getRelationAsHTMLTable($relation) {
	$result = '<table class="wiki-data-table">';	
	$heading = $relation->getHeading();
	
	foreach(getHeadingAsTableHeaderRows($heading) as $headerRow)
		$result .= '<tr>' . $headerRow . '</tr>';
	
	for($i = 0; $i < $relation->getTupleCount(); $i++) {
		$tuple = $relation->getTuple($i);
		$result .= '<tr>' . getTupleAsTableCells($heading, $tuple) .'</tr>';
	}
	
	$result .= '</table>';

	return $result;
}

function getRelationAsSuggestionTable($id, $sourceRelation, $displayRelation) {
	$result = '<table id="' . $id .'" class="wiki-data-table">';	
	$heading = $displayRelation->getHeading();
	$key = $sourceRelation->getKey();
	
	foreach(getHeadingAsTableHeaderRows($heading) as $headerRow)
		$result .= '<tr>' . $headerRow . '</tr>';
	
	for($i = 0; $i < $displayRelation->getTupleCount(); $i++) {
		$tuple = $displayRelation->getTuple($i);
		$id = getTupleKeyName($sourceRelation->getTuple($i), $key);
		$result .= '<tr id="'. $id .'" class="suggestion-row inactive" onclick="suggestRowClicked(this)" onmouseover="mouseOverRow(this)" onmouseout="mouseOutRow(this)">' . getTupleAsTableCells($heading, $tuple) .'</tr>';
	}
	
	$result .= '</table>';

	return $result;
}

function getAddRowAsHTML($addId, $heading, $repeatInput, $allowRemove) {
	if ($repeatInput)
		$rowClass = 'repeat';
	else 
		$rowClass = '';
		
	$result = '<tr id="'. $addId. '" class="' . $rowClass . '">';
	
	if ($allowRemove)
		$result .= '<td/>';
	
	$result .= getHeadingAsAddCells($addId . "-", $heading);
				
	if ($repeatInput)
		$result .= '<td class="add"/>';
		
	return $result . '</tr>'; 
}

function getHeadingAsAddCells($addId, $heading, &$startColumn = 0) {
	global
		$identicalMeaningAttribute;
	
	$result = '';
	
	foreach($heading->attributes as $attribute) {
		$type = $attribute->type;
		
		if (is_a($type, TupleType))
			$result .= getHeadingAsAddCells($addId . $attribute->id . '-', $type->getHeading(), $startColumn);
		else {
			if ($attribute == $identicalMeaningAttribute)
				$value = true;
			else
				$value = "";
				
			$result .= '<td class="'. $type .' column-'. parityClass($startColumn) . '">' . getInputFieldForType($addId . $attribute->id, $type, $value) . '</td>';
			$startColumn++;
		}
	}
	
	return $result;
}

function getRelationAsEditHTML($sourceRelation, $displayRelation, $addId, $removeId, $updateId, $repeatInput, $allowAdd, $allowRemove, $updatableHeading) {
	$displayHeading = $displayRelation->getHeading();
	
	$result = '<table class="wiki-data-table">';	
	$key = $sourceRelation->getKey();
	
	$headerRows = getHeadingAsTableHeaderRows($displayHeading);

	if ($allowRemove)
		$headerRows[0] = '<th class="remove" rowspan="' . count($headerRows) . '"><img src="skins/amethyst/delete.png" title="Mark rows to remove" alt="Remove"/></th>' . $headerRows[0];
		
	if ($repeatInput)		
		$headerRows[0] .= '<th class="add" rowspan="' . count($headerRows) . '">Input rows</th>';
		
	foreach ($headerRows as $headerRow)
		$result .= '<tr>' . $headerRow . '</tr>';
	
	for ($i = 0; $i < $sourceRelation->getTupleCount(); $i++) {
		$result .= '<tr>';
		$tupleKeyName = getTupleKeyName($sourceRelation->getTuple($i), $key);
		
		if ($allowRemove)
			$result .= '<td class="remove">' . getRemoveCheckBox($removeId . $tupleKeyName) . '</td>';
		
		$displayTuple = $displayRelation->getTuple($i);
		$result .= getTupleAsEditTableCells($displayTuple, $updateId . $tupleKeyName . '-', $updatableHeading);
		
		if ($repeatInput)
			$result .= '<td/>';
		
		$result .= '</tr>';
	}
	
	if ($allowAdd) 
		$result .= getAddRowAsHTML($addId, $displayHeading, $repeatInput, $allowRemove);
	
	$result .= '</table>';

	return $result;
}


?>
