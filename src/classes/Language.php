<?php
namespace YaleREDCap\REDCapPRO;

class Language {
    const LANGUAGE_PREFIX = 'language_strings_';
    public ?int $project_id;
    public function __construct(
        private REDCapPRO $module
    ) {
        $this->project_id = $this->module->framework->getProjectId();
    }

    public function getLanguages(bool $activeOnly, bool $setBuiltin = false): array
    {
        if (empty($this->project_id)) {
            return [];
        }
        $languagesJSON = $this->module->framework->getProjectSetting('languages', $this->project_id) ?? '[]';
        $languages = json_decode($languagesJSON, true);
        $builtInLanguages = $this->getBuiltInLanguages();
        $builtInLanguageCodes = array_keys($builtInLanguages);
        $languages = array_filter($languages, function ($lang) use ($builtInLanguageCodes) {
            return $lang['built_in'] !== true || in_array($lang['code'], $builtInLanguageCodes, true);
        });
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

        $defaultLanguage = $this->module->framework->getProjectSetting('reserved-language-project', $this->project_id) ?? 'English';
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
                    'built_in' => true,
                    'direction' => 'ltr'
                ]
            ];
        }
        return $languages; 
    }

    public function getActiveCustomSystemLanguages(): array
    {

        $systemLanguages = $this->module->framework->getSubSettings('add-language', null) ?? [];
        $activeCustomSystemLanguages = [];
        foreach ($systemLanguages as $lang) {
            if (!empty($lang['language-active'])) {
                $activeCustomSystemLanguages[$lang['language-code']] = $lang['language-file'];
            }
        }
        return $activeCustomSystemLanguages;
    }

    public function getBuiltInLanguages(bool $includeCustomSystemLanguages = true): array
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
        if ($includeCustomSystemLanguages) {
            $customSystemLanguages = $this->getActiveCustomSystemLanguages();
            $langs = array_merge($langs, $customSystemLanguages);
        }
        return $langs;
    }

    public function getEnglishStrings(): array
    {
        try {
            $builtInLanguages = $this->getBuiltInLanguages(false);
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
            $this->module->framework->log("Error loading English language strings: " . $e->getMessage());
            return [];
        }
    }

    public function getCurrentLanguage(): ?string
    {
        if (empty($this->project_id)) {
            return null;
        }
        $newLanguage = urldecode($_GET['language'] ?? '');
        if ($newLanguage !== '' && array_key_exists($newLanguage, $this->getLanguages(true, true))) {
            return $newLanguage;
        }
        $defaultLanguage = $this->module->framework->getProjectSetting('reserved-language-project', $this->project_id);
        return $_COOKIE[$this->module->APPTITLE . "_language"] ?? $defaultLanguage;
    }

    public function getCurrentLanguageDirection(): string
    {
        $currentLanguage = $this->getCurrentLanguage();
        $languages = $this->getLanguages(false);
        if (array_key_exists($currentLanguage, $languages) && array_key_exists('direction', $languages[$currentLanguage])) {
            return $languages[$currentLanguage]['direction'];
        }
        return 'ltr';
    }

    public function storeLanguageChoice(string $lang_code): void
    {
        \Session::savecookie($this->module->APPTITLE . "_language", $lang_code, 0, TRUE);
    }

    /**
     * This replaces strings in the following order: 
     * 1. system default language
     * 2. project default language
     * 3. project selected language
     * 
     * So if a string is defined in the project selected language, then it will be used
     * If not, but it is defined in the project default language, then that will be used
     * If it is not defined in either of those, then the system default language will be used
     * If it is not defined in any of those, then the English built-in language will be used (which should have all strings defined)
     * @param string $lang_code
     * @return void
     */
    public function selectProjectLanguage(string $lang_code, $project_id): void
    {
        if (empty($this->project_id)) {
            throw new REDCapProException("Project ID is not set. Cannot select project language.");
        }
        $systemDefaultLanguage = $this->getDefaultSystemLanguage();
        $projectDefaultLanguage = $this->getDefaultProjectLanguage($project_id);
        $selectedLanguage = $lang_code;

        try {
            $this->selectLanguage($systemDefaultLanguage, false);
            $this->selectLanguage($projectDefaultLanguage, false);
            $this->selectLanguage($selectedLanguage);
        } catch ( \Exception $e ) {
            $this->module->logError("Error selecting project language", $e);
        }

    }

    public function selectLanguage(string $lang_code, bool $projectActiveOnly = true): void
    {
        $this->module->framework->log("Selecting language: " . $lang_code);
        $languages = $this->getLanguages($projectActiveOnly);
        if ( !array_key_exists($lang_code, $languages) ) {
            // $lang_code = $this->getDefaultSystemLanguage();
            throw new \Exception("Language code not found or not active: " . $lang_code);
        }
        $lang_strings = $this->getLanguageStrings($lang_code);
        $this->replaceLanguageStrings($lang_strings);
    }

    private function parseIniFileFromEdocId($edocId, $processSections = false)
    {
        try {
            [$mimeType, $filename, $fileContent] = \REDCap::getFile($edocId);
            $result = parse_ini_string($fileContent, $processSections);
            return $result;
        } catch ( \Throwable $e ) {
            $this->logError("Error parsing INI file from edoc ID", $e);
            return false;
        }
    }
    
    private function getBuiltInLanguageStrings(string $lang_code): array
    {
        $builtInLanguages = $this->getBuiltInLanguages(false);
        $isBuiltIn = array_key_exists($lang_code, $builtInLanguages);
        if ($isBuiltIn) {
            $file_path = $builtInLanguages[$lang_code];
            $this->module->framework->log("Loading built-in language file for language code " . $lang_code . " from path: " . $file_path);
            if (!file_exists($file_path)) {
                throw new \Exception("Language file does not exist at path: " . $file_path);
            }
            $lang_strings = parse_ini_file($file_path);
            if (!is_array($lang_strings)) {
                throw new \Exception("Language file did not return an array of strings: " . $file_path);
            }
            return $lang_strings;
        }
        $customLanguages = $this->getActiveCustomSystemLanguages();
        if (array_key_exists($lang_code, $customLanguages)) {
            $file_path = $customLanguages[$lang_code];
            $this->module->framework->log("Loading custom system language file for language code " . $this->module->escape($lang_code) );
            $lang_strings = $this->parseIniFileFromEdocId($file_path);
            if (empty($lang_strings)) {
                throw new \Exception("Custom system language file did not return an array of strings");
            }
            return $lang_strings;
        }
        return [];
    }

    private function isBuiltInLanguage(string $lang_code): bool
    {
        $builtInLanguages = $this->getBuiltInLanguages();
        return array_key_exists($lang_code, $builtInLanguages);
    }

    public function getLanguageStrings(string $lang_code): array
    {
        if (empty($this->project_id)) {
            return [];
        }
        if ($this->isBuiltInLanguage($lang_code)) {
            return $this->getBuiltInLanguageStrings($lang_code);
        }

        $settingName = self::LANGUAGE_PREFIX . $lang_code;
        $languageJSON = $this->module->framework->getProjectSetting($settingName, $this->project_id);
        return json_decode($languageJSON ?? '[]', true);
    }

    public function setLanguageActiveStatus(string $lang_code, bool $active): void
    {
        if (empty($this->project_id)) {
            return;
        }
        $languages = $this->getLanguages(false);
        $languages[$lang_code] = $languages[$lang_code] ?? [];
        $languages[$lang_code]['code'] = $lang_code;
        $languages[$lang_code]['active'] = $active;
        $this->module->framework->setProjectSetting('languages', json_encode($languages), $this->project_id);
    }

    public function setLanguageStrings(string $lang_code, array $lang_strings, string|null $direction): void
    {
        if (empty($this->project_id)) {
            return;
        }
        $languageStringsSettingName = self::LANGUAGE_PREFIX . $lang_code;
        $this->module->framework->setProjectSetting($languageStringsSettingName, json_encode($lang_strings), $this->project_id);
        $languages = $this->getLanguages(false);
        $languages[$lang_code] = $languages[$lang_code] ?? [];
        $languages[$lang_code]['code'] = $lang_code;
        if (!empty($direction)) {
            $languages[$lang_code]['direction'] = $direction;
        }
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

    public function getDefaultSystemLanguage() : string
    {
        $defaultSetting = $this->module->framework->getSystemSetting('reserved-language-system') ?? 'English';
        $builtInLanguages = $this->getBuiltInLanguages();
        if (array_key_exists($defaultSetting, $builtInLanguages)) {
            return $defaultSetting;
        }
        return 'English';
    }

    public function getDefaultProjectLanguage($project_id) : string
    {
        $defaultSetting = $this->module->framework->getProjectSetting('reserved-language-project', $project_id);
        $languages = $this->getLanguages(true);
        if (array_key_exists($defaultSetting, $languages)) {
            return $defaultSetting;
        }
        return $this->getDefaultSystemLanguage();
    }

    public function handleLanguageChangeRequest() : void
    {
        if (empty($this->project_id)) {
            return;
        }
        try {
            $currentLanguage   = $this->getCurrentLanguage() ?? $this->getDefaultProjectLanguage($this->project_id);
            $requestedLanguage = urldecode($_GET['language']) ?? null;
            if ( isset($requestedLanguage) && !empty($requestedLanguage) && array_key_exists($requestedLanguage, $this->getLanguages(true, true)) ) {
                $this->storeLanguageChoice($requestedLanguage);
                $currentLanguage = $requestedLanguage;
            }    
            $this->selectProjectLanguage($currentLanguage, $this->project_id);
        } catch (\Exception $e) {
            $this->module->framework->log("Error selecting language: " . $e->getMessage());
            $defaultSystemLanguage = $this->getDefaultSystemLanguage();
            $this->selectLanguage($defaultSystemLanguage);
        }
    }

    public function deleteLanguage(string $lang_code): void
    {
        $languages = $this->getLanguages(false);
        if (!array_key_exists($lang_code, $languages)) {
            throw new \Exception("Language code not found: " . $lang_code);
        }
        if (isset($languages[$lang_code]['built_in']) && $languages[$lang_code]['built_in'] === true) {
            throw new \Exception("Cannot delete built-in language: " . $lang_code);
        }
        unset($languages[$lang_code]);
        $this->module->framework->setProjectSetting('languages', json_encode($languages), $this->project_id);
        $languageStringsSettingName = self::LANGUAGE_PREFIX . $lang_code;
        $this->module->framework->setProjectSetting($languageStringsSettingName, null, $this->project_id);
    }
    
}