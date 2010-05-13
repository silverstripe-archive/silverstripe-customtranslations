<?php

class CustomTranslationAdmin extends ModelAdmin {
	static $url_segment = 'translations';

	static $managed_models = "CustomLanguageTranslation";

	static $menu_title = 'Translations';

	public static $collection_controller_class = "CustomTranslationAdmin_CollectionController";
}

class CustomTranslationAdmin_CollectionController extends ModelAdmin_CollectionController {
	/**
	 * Creates and returns the result table field for resultsForm.
	 * Uses {@link resultsTableClassName()} to initialise the formfield.
	 * Method is called from {@link ResultsForm}.
	 *
	 * @param array $searchCriteria passed through from ResultsForm
	 *
	 * @return TableListField
	 */
	function getResultsTable($searchCriteria) {
		$summaryFields = $this->getResultColumns($searchCriteria);

		$className = $this->parentController->resultsTableClassName();
		$tf = new $className(
			$this->modelClass,
			$this->modelClass,
			$summaryFields
		);

		// Force TableListField js before custom translation js
		Requirements::javascript(SAPPHIRE_DIR . '/javascript/TableListField.js');
		Requirements::javascript("customtranslations/javascript/CustomTranslationAdmin.js");

		$tf->setCustomSourceItems($this->itemsWithFiltering($searchCriteria));
		$tf->setPageSize($this->parentController->stat('page_length'));
		$tf->setShowPagination(true);
		$tf->itemClass = "CustomTranslationTableListField_Item";
		$tf->actions = array(
			// Delete an existing mapping
			'delete' => array(
				'label' => 'Delete',
				'icon' => 'cms/images/delete.gif',
				'icon_disabled' => 'cms/images/delete_disabled.gif',
				'class' => 'deletelink'
			),
			// Add a new mapping
			'add' => array(
				'label' => 'Add',
				'icon' => 'customtranslations/images/add.gif',
				'icon_disabled' => 'customtranslations/images/add_disabled.gif',
				'class' => 'addlink'
			)
		);


		$tf->setPermissions(array_merge(array('view','export'), TableListField::permissions_for_object($this->modelClass)));

		// csv export settings (select all columns regardless of user checkbox settings in 'ResultsAssembly')
		$exportFields = $this->getResultColumns($searchCriteria, false);
		$tf->setFieldListCsv($exportFields);

		return $tf;
	}

	/**
	 * Return a DataObjectSet that contains all the items that apply to the search criteria. This is basically
	 * a list of all language settings from $lang that match the search Criteria, with overrides loaded from the
	 * database and merged together. Sowe get a list of all matching items, and showing where they have been overridden.
	 * @return DataObjectSet
	 */
	function itemsWithFiltering($searchCriteria) {
		global $lang;

		// Keep the base lang
		$langOld = $lang;

		// Reload lang with all the common locals
		$lang = null;

		// Load this first, others depend on it.
		i18n::include_by_locale('en_US', true, true);
		foreach (i18n::$common_locales as $locale => $name) {
			i18n::include_by_locale($locale, false, true);
		}

		// Iterate over $lang and add anything to the dataset that matches the searchCriteria.
		$result = new DataObjectSet();
		foreach ($lang as $locale => $classes) {
			if (isset($searchCriteria["Locale"]) &&
				$searchCriteria["Locale"] &&
				strpos($locale, $searchCriteria["Locale"]) === false) continue;
			foreach ($classes as $class => $entities) {
				foreach ($entities as $entity => $translation) {
					$combined = $class . "." . $entity;
					if (isset($searchCriteria["Entity"]) &&
						$searchCriteria["Entity"] &&
						strpos($combined, $searchCriteria["Entity"]) === false) continue;

					if (is_array($translation)) {
						$trans = array_shift($translation);
						$priority = array_shift($translation);
						$comment = array_shift($translation);
					}
					else {
						$trans = $translation;
						$priority = PR_MEDIUM;
						$comment = null;
					}

					if (isset($searchCriteria["Translation"]) &&
						$searchCriteria["Translation"] &&
						strpos($trans, $searchCriteria["Translation"]) === false) continue;



					$item = new CustomLanguageTranslation(array(
						'ID' => 0,
						'ClassName' => "CustomTranslationAdmin",
						'RecordClassName' => "CustomTranslationAdmin",
						'Locale' => $locale,
						'Entity' => $combined,
						'Translation' => $trans,
						'Priority' => $priority,
						'Comment' => $comment,
						'Link' => $this->Link()
					));

					$result->push($item);
				}
			}
		}

		// Get the query that will give us the overrides to merge in.
		$query = $this->getSearchQuery($searchCriteria);
		$records = $query->execute();
		$dataobject = new CustomLanguageTranslation();
		$items = $dataobject->buildDataObjectSet($records, 'DataObjectSet');

		if ($items) {
			// there are overrides that match, so merge these into $result. In general we should always find the
			// record already there, and we just update it, otherwise we add it.
			foreach ($items as $item) {
				$found = false;
				foreach ($result as $r) {
					if ($r->Locale == $item->Locale &&
						$r->Entity == $item->Entity) {
						$found = true;
						break;
					}
				}

				if ($found) {
					$r->OriginalTranslation = $r->Translation;
					$r->OriginalPriority = $r->Priority;
					$r->OriginalComment = $r->Comment;
					$r->Translation = $item->Translation;
					$r->Priority = $item->Priority;
					$r->Comment = $item->Comment;
					$r->ID = $item->ID;
				}
				else
					$result->push($item);
			}
		}

		$lang = $langOld;

		return $result;
	}

	public function AddForm() {
		$newRecord = new $this->modelClass();

		foreach (array("Locale", "Entity", "Translation", "Priority", "Comment", "OriginalTranslation", "OriginalPriority", "OriginalComment") as $field) {
			if (isset($_REQUEST[$field])) $newRecord->$field = $_REQUEST[$field];
		}

		if($newRecord->canCreate()){
			if($newRecord->hasMethod('getCMSAddFormFields')) {
				$fields = $newRecord->getCMSAddFormFields();
			} else {
				$fields = $newRecord->getCMSFields();
			}

			$validator = ($newRecord->hasMethod('getCMSValidator')) ? $newRecord->getCMSValidator() : null;
			if(!$validator) $validator = new RequiredFields();
			$validator->setJavascriptValidationHandler('none');

			$actions = new FieldSet (
				new FormAction("doCreate", _t('ModelAdmin.ADDBUTTON', "Add"))
			);

			$form = new Form($this, "AddForm", $fields, $actions, $validator);
			$form->loadDataFrom($newRecord);

			return $form;
		}
	}
}

/**
 * Provide custom behaviour of list items.
 */
class CustomTranslationTableListField_Item extends TableListField_Item {
	// Basically a version that doesn't use default item formatting.
	function Fields($xmlSafe = true) {
		$list = $this->parent->FieldList();
		foreach($list as $fieldName => $fieldTitle) {
			$value = "";

			// This supports simple FieldName syntax
			if(strpos($fieldName,'.') === false) {
				$value = ($this->item->XML_val($fieldName) && $xmlSafe) ? $this->item->XML_val($fieldName) : $this->item->RAW_val($fieldName);
			// This support the syntax fieldName = Relation.RelatedField
			} else {
				$fieldNameParts = explode('.', $fieldName)	;
				$tmpItem = $this->item;
				for($j=0;$j<sizeof($fieldNameParts);$j++) {
					$relationMethod = $fieldNameParts[$j];
					$idField = $relationMethod . 'ID';
					if($j == sizeof($fieldNameParts)-1) {
						if($tmpItem) $value = $tmpItem->$relationMethod;
					} else {
						if($tmpItem) $tmpItem = $tmpItem->$relationMethod();
					}
				}
			}

			// casting
			if(array_key_exists($fieldName, $this->parent->fieldCasting)) {
				$value = $this->parent->getCastedValue($value, $this->parent->fieldCasting[$fieldName]);
			} elseif(is_object($value) && method_exists($value, 'Nice')) {
				$value = $value->Nice();
			}

			// formatting. If there is a override here, make all fields hyperlinked. We also include in the URL
			// the original fields so we can display them too.
			if ($this->item->ID) {
				$orig = "?OriginalTranslation="	. urlencode($this->item->OriginalTranslation) .
						"&OriginalPriority=" . urlencode($this->item->OriginalPriority) .
						"&OriginalComment=" . urlencode($this->item->OriginalComment);
				$value = "<a href=\"" . $this->item->Link . "/{$this->item->ID}/edit/$orig\">$value</a>";
			}

			//escape
			if($escape = $this->parent->fieldEscape){
				foreach($escape as $search => $replace){
					$value = str_replace($search, $replace, $value);
				}
			}

			$fields[] = new ArrayData(array(
				"Name" => $fieldName,
				"Title" => $fieldTitle,
				"Value" => $value,
				"CsvSeparator" => $this->parent->getCsvSeparator(),
			));
		}
		return new DataObjectSet($fields);
	}

	function Actions() {
		$allowedActions = new DataObjectSet();
		foreach($this->parent->actions as $actionName => $actionSettings) {
			$can = $this->Can($actionName);
			if ((!$this->item->ID && $actionName == "delete") ||
				($this->item->ID && $actionName == "add")) $can = false;
			if($this->parent->Can($actionName)) {
				$allowedActions->push(new ArrayData(array(
					'Name' => $actionName,
					'Link' => $this->{ucfirst($actionName).'Link'}(),
					'Icon' => $actionSettings['icon'],
					'IconDisabled' => $actionSettings['icon_disabled'],
					'Label' => $actionSettings['label'],
					'Class' => $actionSettings['class'],
					'Default' => ($actionName == $this->parent->defaultAction),
					'IsAllowed' => $can,
				)));
			}
		}

		return $allowedActions;
	}

	function AddLink() {
		$link = $this->item->Link . "/add";
		foreach (array(
			"?Locale" => $this->item->Locale,
			"&Entity" => $this->item->Entity,
			"&Translation" => $this->item->Translation,
			"&Priority" => $this->item->Priority,
			"&Comment" => $this->item->Comment,
			"&OriginalTranslation" => $this->item->Translation,
			"&OriginalPriority" => $this->item->Priority,
			"&OriginalComment" => $this->item->Comment
		) as $formField => $value) $link .= "{$formField}=" . urlencode($value);
		return $link;
	}
}
