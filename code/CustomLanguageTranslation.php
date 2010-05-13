<?php

/**
 * Provides configurable overrides for language translations.
 */
class CustomLanguageTranslation extends DataObject {
	static $db = array(
		"Locale" => "Varchar(6)",

		// Entity of this override, a dot-separated identifier for the string we're looking up, that is
		// passed into _t.
		// passed into _t.
		"Entity" => "Varchar(255)",

		// What it translates to in this locale
		"Translation" => "Text",

		// Priority defined in lang. If zero, uses PR_MEDIUM
		"Priority" => "Int",

		// Explanatory comments
		"Comment" => "Text"
	);

	static $summary_fields = array('Locale', 'Entity', 'Translation');
	static $searchable_fields = array('Locale', 'Entity', 'Translation');


 	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab('Root.Main', new HeaderField('_newDesc', 'Custom Translation', 4));
		$fields->addFieldToTab('Root.Main', new TextField('Locale'));
		$fields->addFieldToTab('Root.Main', new TextField('Entity'));
		$fields->addFieldToTab('Root.Main', new TextField('Translation'));
		$fields->addFieldToTab('Root.Main', new TextField('Priority'));
		$fields->addFieldToTab('Root.Main', new TextField('Comment'));
		if (isset($_REQUEST['OriginalTranslation']) || isset($_REQUEST['OriginalPriority']) || isset($_REQUEST['OriginalComment']))
			$fields->addFieldToTab('Root.Main', new HeaderField('_origDesc', 'Values in the lang entry being overridden:', 4));
		if (isset($_REQUEST['OriginalTranslation']))
			$fields->addFieldToTab(
				'Root.Main',
				new ReadonlyField('OriginalTranslation', 'Translation in lang file', $_REQUEST['OriginalTranslation'])
			);
		if (isset($_REQUEST['OriginalPriority']))
			$fields->addFieldToTab(
				'Root.Main',
				new ReadonlyField('OriginalPriority', 'Priority in lang file', $_REQUEST['OriginalPriority'])
			);
		if (isset($_REQUEST['OriginalComment']))
			$fields->addFieldToTab(
				'Root.Main',
				new ReadonlyField('OriginalComment', 'Comment in lang file', $_REQUEST['OriginalTranslation'])
			);
		return $fields;
	}

	/**
	 * Return a validator.
	 * @return void
	 */
	public function getCMSValidator() {
		return new CustomTranslationValidator(array('Translation'));
	}

	/**
	 * A callback passed into i18n::register_translation_provider, which is called the first time a
	 * locale is loaded. Basically it retrieves all the translations from CustomLanguageTranslation
	 * that are in that locale, pulling apart the entities to create the correctly structured array.
	 * @static
	 * @param  $locale
	 * @return array		Returns an array that can be merged with $lang.
	 */
	static function custom_translations($locale) {
		$result = array();
		$trans = DataObject::get("CustomLanguageTranslation", "\"Locale\"='$locale'");
		if ($trans) foreach ($trans as $t) {
			$parts = explode(".", $t->Entity);
			if (count($parts) != 2) continue;
			$class = $parts[0];
			$entity = $parts[1];
			$result[$locale][$class][$entity] = array($t->Translation, $t->Priority ? $t->Priority : PR_MEDIUM, $t->Comment); 
		}
		return $result;
	}

}

/**
 * A custom validator for translations. It implements validation logic in PHP for matching signatures in the translation
 * text. It parses the original translation (from the language file) for occurence of %s, %d and the like, and does
 * the same for the new validation text. The validation only succeeds if there are the same number of tokens, and
 * of the same type in the same order. Failure to enforce this could mean arbitrary run-time errors in the site,
 * where replacements don't work.
 */
class CustomTranslationValidator extends RequiredFields {
	function php($data) {
		if (!parent::php($data)) return false;  // required fields failed.

		$origTokens = $this->getTokens($data['OriginalTranslation']);
		$newTokens = $this->getTokens($data['Translation']);

		// determine if they are the same
		$valid = count($origTokens) == count($newTokens);

		if ($valid && count($origTokens) > 0) {
			// Counts are the same, so iterate over both arrays and ensure they are compatible.
			for ($i = 0; $i < count($origTokens); $i++) {
				// @todo This requires an exact match. For example, %10d and %d won't match, but should.
				if ($origTokens[$i] != $newTokens[$i]) $valid = false;
			}
		}

		if (!$valid) {
			$this->validationError(
				'Translation',
				_t('Form.FIELDSIGNATUREMISMATCH', "The value substitution tokens in the new translation must match the tokens in the original translation string"),
				"invalid"
			);
		}

		return $valid;
	}

	/**
	 * Scan a string for sprintf %-style tokens. Return an array with the tokens. If there are no tokens, return
	 * an empty array. It parses %% as well, but doesn't return them, as they are treated as literal by sprintf.
	 * @param  $s
	 * @return void
	 */
	protected function getTokens($s) {
		preg_match_all("/\%[sd%]/", $s, $matches, PREG_PATTERN_ORDER);
		return array_filter($matches[0], create_function('$s', 'return $s!="%%";'));
	}
}
