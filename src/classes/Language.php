<?php
namespace YaleREDCap\REDCapPRO;

class Language {
    const LANGUAGE_PREFIX = 'language_strings_';
    public int $project_id;
    public function __construct(
        private REDCapPRO $module
    ) {
        $this->project_id = $this->module->framework->getProjectId();
    }

    public function getLanguages(bool $activeOnly, bool $setBuiltin = false): array
    {
        $languagesJSON = $this->module->framework->getProjectSetting('languages', $this->project_id) ?? '[]';
        $languages = json_decode($languagesJSON, true);
        
        
        $builtInLanguages = $this->getBuiltInLanguages();
        foreach ($builtInLanguages as $lang_code => $file_path) {
            if (!isset($languages[$lang_code])) {
                $thisLang = [
                    'code' => $lang_code,
                    'active' => false,
                    'built_in' => true
                ];
                if ($setBuiltin) { 
                    $this->setLanguageActiveStatus($lang_code, false);
                }
                $languages[$lang_code] = $thisLang;
            }
        }

        $defaultLanguage = $this->module->framework->getProjectSetting('reserved-language-project', $this->project_id);

        if ($activeOnly) {
            $languages = array_filter($languages, function ($lang) use ($defaultLanguage) {
                return $lang['active'] === true || $lang['code'] === $defaultLanguage;
            });
        }
        if (empty($languages)) {
            $languages = [
                'English' => [
                    'code' => 'English',
                    'active' => true,
                    'built_in' => true
                ]
            ];
        }
        return $languages; 
    }

    public function getBuiltInLanguages(): array
    {
        $langs = array();
        $path  = $this->module->framework->getModulePath() . DS . "lang" . DS;
        if ( is_dir($path) ) {
            $files = glob($path . "*.{i,I}{n,N}{i,I}", GLOB_BRACE);
            foreach ( $files as $filename ) {
                if ( is_file($filename) ) {
                    $thisLang         = pathinfo($filename, PATHINFO_FILENAME);
                    $langs[$thisLang] = $filename;
                }
            }
        }
        return $langs;
    }

    public function getEnglishStrings(): array
    {
        try {
            $builtInLanguages = $this->getBuiltInLanguages();
            if (!array_key_exists('English', $builtInLanguages)) {
                throw new \Exception("English language file not found in built-in languages.");
            }
            $file_path = $builtInLanguages['English'];
            if (!file_exists($file_path)) {
                throw new \Exception("English language file does not exist at path: " . $file_path);
            }
            $lang_strings = parse_ini_file($file_path, true);
            if (!is_array($lang_strings)) {
                throw new \Exception("English language file did not return an array of strings: " . $file_path);
            }
            return $lang_strings;
        } catch ( \Exception $e ) {
            $this->module->log("Error loading English language strings: " . $e->getMessage());
            return [];
        }
    }

    public function getCurrentLanguage(): ?string
    {
        $newLanguage = urldecode($_GET['language'] ?? '');
        if ($newLanguage !== '' && array_key_exists($newLanguage, $this->getLanguages(true, true))) {
            return $newLanguage;
        }
        $defaultLanguage = $this->module->framework->getProjectSetting('reserved-language-project', $this->project_id);
        return $_COOKIE[$this->module->APPTITLE . "_language"] ?? $defaultLanguage;
    }

    public function storeLanguageChoice(string $lang_code): void
    {
        \Session::savecookie($this->module->APPTITLE . "_language", $lang_code, 0, TRUE);
    }

    public function selectLanguage(string $lang_code): void
    {
        $languages = $this->getLanguages(true);
        if ( !array_key_exists($lang_code, $languages) ) {
            throw new \Exception("Language code not found or not active: " . $lang_code);
        }
        if (isset($languages[$lang_code]['built_in']) && $languages[$lang_code]['built_in'] === true) {
            $lang_strings = $this->getBuiltInLanguageStrings($languages[$lang_code]);
        } else {
            $lang_strings = $this->getLanguageStrings($lang_code);
        }
        $this->replaceLanguageStrings($lang_strings);
    }
    
    private function getBuiltInLanguageStrings(array $language): array 
    {
        $builtInLanguages = $this->getBuiltInLanguages();
        if (!array_key_exists($language['code'], $builtInLanguages)) {
            throw new \Exception("Built-in language code not found: " . $language['code']);
        }
        $file_path = $builtInLanguages[$language['code']];
        $this->module->log("Loading built-in language file for language code " . $language['code'] . " from path: " . $file_path);
        if (!file_exists($file_path)) {
            throw new \Exception("Language file does not exist at path: " . $file_path);
        }
        $lang_strings = parse_ini_file($file_path);
        if (!is_array($lang_strings)) {
            throw new \Exception("Language file did not return an array of strings: " . $file_path);
        }
        return $lang_strings;
    }

    public function getLanguageStrings(string $lang_code): array
    {
        $settingName = self::LANGUAGE_PREFIX . $lang_code;
        $languageJSON = $this->module->framework->getProjectSetting($settingName, $this->project_id);
        return json_decode($languageJSON ?? '[]', true);
    }

    public function setLanguageActiveStatus(string $lang_code, bool $active): void
    {
        $languages = $this->getLanguages(false);
        $languages[$lang_code] = $languages[$lang_code] ?? [];
        $languages[$lang_code]['code'] = $lang_code;
        $languages[$lang_code]['active'] = $active;
        $this->module->framework->setProjectSetting('languages', json_encode($languages), $this->project_id);
    }

    public function setLanguageStrings(string $lang_code, array $lang_strings): void
    {
        $languageStringsSettingName = self::LANGUAGE_PREFIX . $lang_code;
        $this->module->framework->setProjectSetting($languageStringsSettingName, json_encode($lang_strings), $this->project_id);
        $languages = $this->getLanguages(false);
        $languages[$lang_code] = $languages[$lang_code] ?? [];
        $languages[$lang_code]['code'] = $lang_code;
        $this->module->framework->setProjectSetting('languages', json_encode($languages), $this->project_id);
    }

    private function replaceLanguageStrings(array $lang_strings): void
    {
        global $lang;
        foreach ($lang_strings as $key => $value) {
            if (empty($value)) {
                continue;
            }
            $em_key = \ExternalModules\ExternalModules::constructLanguageKey($this->module->PREFIX, $key);
            $lang[$em_key] = $value;
        }
    }

    public function handleLanguageChangeRequest() : void
    {
        $defaultLanguage = $this->module->framework->getProjectSetting('reserved-language-project', $this->project_id) ?? 'English';
        $currentLanguage   = $this->getCurrentLanguage();
        $requestedLanguage = urldecode($_GET['language']) ?? null;
        if ( isset($requestedLanguage) && array_key_exists($requestedLanguage, $this->getLanguages(true, true)) ) {
            $this->storeLanguageChoice($requestedLanguage);
            $currentLanguage = $requestedLanguage;
        }
        try {
            $this->selectLanguage($currentLanguage ?? $defaultLanguage);
        } catch (\Exception $e) {
            $this->module->log("Error selecting language: " . $e->getMessage());
            $this->selectLanguage($defaultLanguage);
        }
    }
    
}