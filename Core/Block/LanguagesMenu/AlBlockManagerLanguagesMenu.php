<?php
/**
 * This file is part of the RedKiteCmsBaseBlocksBundle and it is distributed
 * under the MIT License. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) RedKite Labs <info@redkite-labs.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.redkite-labs.com
 *
 * @license    MIT License
 *
 */

namespace RedKiteCms\Block\RedKiteCmsBaseBlocksBundle\Core\Block\LanguagesMenu;

use RedKiteLabs\RedKiteCmsBundle\Core\Content\Block\AlBlockManagerContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use RedKiteLabs\RedKiteCmsBundle\Core\Content\Validator\AlParametersValidatorInterface;
use RedKiteLabs\ThemeEngineBundle\Core\Asset\AlAsset;
use RedKiteLabs\RedKiteCmsBundle\Core\Content\Block\JsonBlock\AlBlockManagerJsonBase;
use RedKiteCms\Block\RedKiteCmsBaseBlocksBundle\Core\Form\LanguagesMenu\LanguagesMenuType;
use Symfony\Component\Finder\Finder;

/**
 * Defines the Block Manager to render a navigation menu for the website's languages.
 * 
 * Menu is renderd as an unordered list
 *
 * @author RedKite Labs <info@redkite-labs.com>
 */
class AlBlockManagerLanguagesMenu extends AlBlockManagerContainer
{
    private $urlManager = null;
    private $kernel = null;
    private $flagsAsset;

    /**
     * Constructor
     * 
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param \RedKiteLabs\RedKiteCmsBundle\Core\Content\Validator\AlParametersValidatorInterface $validator
     */
    public function __construct(ContainerInterface $container, AlParametersValidatorInterface $validator = null)
    {
        parent::__construct($container, $validator);

        $this->languageRepository = $this->factoryRepository->createRepository('Language');
        $this->urlManager = $this->container->get('red_kite_cms.url_manager');
        $this->kernel = $this->container->get('kernel');
        $flagsFolder = $this->container->getParameter('red_kite_cms.flags_folder');
        $this->flagsAsset = new AlAsset($this->kernel, $flagsFolder); 
    }

    /**
     *  {@inheritdoc}
     */
    public function getDefaultValue()
    {
        return array("Content" => json_encode($this->generateValues()));
    }
    
    /**
     *  {@inheritdoc}
     */
    public function editorParameters()
    {
        $value = AlBlockManagerJsonBase::decodeJsonContent($this->alBlock->getContent());
   
        $flagsDirectories = array();
        $finder = new Finder();
        $folders = $finder->directories()->depth(0)->in($this->flagsAsset->getRealPath());
        foreach ($folders as $folder) {
            $flagDirectory = basename($folder->getFileName());
            $flagsDirectories[$flagDirectory] = $flagDirectory;
        }
        
        $formClass = new LanguagesMenuType($flagsDirectories, $value["languages"], $value["imagesFolder"]);
        $form = $this->container->get('form.factory')->create($formClass);

        return array(
            "template" => 'RedKiteCmsBaseBlocksBundle:Editor:LanguagesMenu/editor.html.twig',
            "title" => $this->translator->translate("navigation_languages_menu_title_editor", array(), 'RedKiteCmsBaseBlocksBundle'),
            "form" => $form->createView(),
        );
    }

    /**
     *  {@inheritdoc}
     */
    protected function renderHtml()
    {
        $contents = $this->generateValues();
        
        return array('RenderView' => array(
            'view' => 'RedKiteCmsBaseBlocksBundle:Content:LanguagesMenu/languages_menu.html.twig',
            'options' => array(
                'languages' => $contents["languages"],
            ),
        ));
    }
    
    /**
     *  {@inheritdoc}
     */
    protected function edit(array $values)
    {
        $values = $this->updateSavedLanguages($values);      

        return parent::edit($values);
    }
    
    /**
     * Updates the content with the right images path for countries
     * 
     * @param array $values
     * @return array
     */
    protected function updateSavedLanguages(array $values)
    {
        if (array_key_exists('Content', $values)) {           
            $languages = array();            
            $unserializedData = array();
            $serializedData = $values['Content'];
            parse_str($serializedData, $unserializedData);
            
            $imagesFolder = $unserializedData["al_json_block"]["flags_directories"];
            unset($unserializedData["al_json_block"]["flags_directories"]);
            
            foreach ($unserializedData["al_json_block"] as $languageName => $country) {
                $language = $this->languageRepository->fromLanguageName($languageName);
                $url = $this->generateUrl($language);

                $countryName = strtolower($country);
                $country = $this->generateCountryPath($imagesFolder, $countryName);
                
                $languages[$languageName] = array(
                    "country" => $country,
                    "url" => $url,
                );
            }
            
            $newValues = array(
                "imagesFolder" => $imagesFolder,
                "languages" => $languages,
            );
            
            $values['Content'] = json_encode($newValues);
        }
        
        return $values;
    }
    
    /**
     * Generates the block's value
     * 
     * @return array
     */
    protected function generateValues()
    {
        $items = array();
        $values = array();
        $imagesFolder = "20x15";
        
        if (null !== $this->alBlock) {
            $values = json_decode($this->alBlock->getContent(), true);
            $items = $values["languages"];
            $imagesFolder = $values["imagesFolder"];
        }
        
        $activeLanguages = $this->languageRepository->activeLanguages();
        foreach ($activeLanguages as $language) {
            $languageName = $language->getLanguageName();            
            $url = (array_key_exists($languageName , $items)) ? $items[$languageName]["url"] : null;
            $url = $this->generateUrl($language, $url);
            
            $country = "";
            if (null !== $items && array_key_exists($languageName, $items)) {
                $country = $items[$languageName]["country"];
            }
            
            if (empty($country) ) {
                $country = $this->generateCountryPath($imagesFolder, $languageName);
            }
            
            $languages[$languageName] = array(
                "country" => $country,
                "url" => $url,
            );
        }
         
        $newValues = array(
            "imagesFolder" => $imagesFolder,  
            "languages" => $languages,
        );
        
        if (! empty($items) && $newValues != $values) {
            parent::edit(array("Content" => json_encode($newValues)));
        }
        
        return $newValues;
    }

    private function generateUrl($language, $url = null)
    {
        if (null === $this->pageTree) {
            $this->pageTree = $this->container->get('red_kite_cms.page_tree');
        }
        $page = $this->pageTree->getAlPage();
        
        if (null === $page && null !== $url) {
            return $url;
        }
        
        $url = $this->urlManager
                    ->buildInternalUrl($language, $page)
                    ->getInternalUrl();
        if (null === $url)  {
            $url = '#';
        }
        
        return $url;
    }
    
    private function generateCountryPath($imagesFolder, $countryName)
    {
        $country = "";
        $countryImage = $this->flagsAsset->getRealPath() . '/' . $imagesFolder . '/' . $countryName . '.png';
        if (file_exists($countryImage)) {
            $country = "/" . $this->flagsAsset->getAbsolutePath() . '/' . $imagesFolder . '/' . $countryName . '.png';
        }
        
        return $country;
    }   
}
