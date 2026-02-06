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
        if ($activeOnly) {
            $languages = array_filter($languages, function ($lang) {
                return $lang['active'] === true;
            });
        }
        
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

        return $languages; 
    }

    private function getBuiltInLanguages(): array
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

    public function getCurrentLanguage(): ?string
    {
        // $currentLanguage = $this->module->framework->getProjectSetting('reserved-language-project', $this->project_id);
        // return $currentLanguage;
        return 'English';
    }

    public function storeLanguageChoice(string $lang_code): void
    {
        \Session::savecookie($this->module->APPTITLE . "_language", $lang_code, 0, TRUE);
    }

    public function selectLanguage(string $lang_code): void
    {
        $lang_strings = $this->getLanguageStrings($lang_code);
        $this->replaceLanguageStrings($lang_strings);
    }

    private function getLanguageStrings(string $lang_code): array
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
            $em_key = \ExternalModules\ExternalModules::constructLanguageKey($this->module->PREFIX, $key);
            $lang[$em_key] = $value;
        }
    }
    
}