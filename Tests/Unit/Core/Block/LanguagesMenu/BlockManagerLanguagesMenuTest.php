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

namespace RedKiteCms\Block\RedKiteCmsBaseBlocksBundle\Tests\Unit\Core\Block\LanguagesMenu;

use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Tests\TestCase;
use RedKiteCms\Block\RedKiteCmsBaseBlocksBundle\Core\Block\LanguagesMenu\BlockManagerLanguagesMenu;
use org\bovigo\vfs\vfsStream;

class BlockManagerLanguagesMenuTester extends BlockManagerLanguagesMenu
{
    public function updateSavedLanguagesTester(array $values)
    {
        return $this->updateSavedLanguages($values);
    }
}

/**
 * BlockManagerLanguagesMenuTest
 *
 * @author RedKite Labs <info@redkite-labs.com>
 */
class BlockManagerLanguagesMenuTest extends \RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Tests\Unit\Core\Content\Block\Base\BlockManagerContainerBase
{
    protected $languageRepository;
    protected $urlManager;
    protected $root;

    protected function setUp()
    {
        parent::setUp();
        
        $folders = array(
            'flags' => 
                array(
                    "20x15" => 
                        array(
                            "fr.png" => "",                            
                            "gb.png" => "",
                        ),
                    "40x30" => 
                        array(
                            "fr.png" => "",                            
                            "gb.png" => "",
                        ),
                )
            );
        $this->root = vfsStream::setup('root', null, $folders);
        
        $this->eventsHandler = $this->getMock('RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\EventsHandler\EventsHandlerInterface');

        $this->languageRepository = $this->getMockBuilder('RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Repository\Propel\LanguageRepositoryPropel')
                                    ->disableOriginalConstructor()
                                    ->getMock();

        $this->factoryRepository = $this->getMock('RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Repository\Factory\FactoryRepositoryInterface');
        $this->factoryRepository->expects($this->at(1))
            ->method('createRepository')
            ->with('Language')
            ->will($this->returnValue($this->languageRepository));

        $this->urlManager = $this->getMock('\RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\UrlManager\UrlManagerInterface');
        $this->urlManager->expects($this->any())
            ->method('buildInternalUrl')
            ->will($this->returnSelf());
        
        $this->kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $this->kernel->expects($this->any())
            ->method('locateResource')
            ->will($this->returnValue(vfsStream::url('root\flags')));
        
        $this->initContainer();
        
        $this->container->expects($this->at(3))
            ->method('get')
            ->with('red_kite_cms.url_manager')
            ->will($this->returnValue($this->urlManager));
        
        $this->container->expects($this->at(4))
            ->method('get')
            ->with('kernel')
            ->will($this->returnValue($this->kernel));
        
        $this->container->expects($this->at(5))
            ->method('getParameter')
            ->with('red_kite_cms.flags_folder')
            ->will($this->returnValue('@NavigationMenuBundle'));
    }

    /**
     * @dataProvider languagesProvider
     */
    public function testDefaultValue($language, $expectedResult)
    {
        $this->initPageTree();
            
        $this->languageRepository->expects($this->once())
            ->method('activeLanguages')
            ->will($this->returnValue(array($this->initLanguage($language))));

        $expectedValue = array("Content" => $expectedResult);
        $blockManager = new BlockManagerLanguagesMenu($this->container);
        $this->assertEquals($expectedValue, $blockManager->getDefaultValue());
    }
    
    /**
     * @dataProvider htmlProvider
     */
    public function testGetHtml($languageNames, $expectedLanguages, $block = null) 
    {
        $this->initPageTree();
        
        $languages = array();
        foreach($languageNames as $languageName) {
            $languages[] = $this->initLanguage($languageName);
        }
        
        $this->languageRepository->expects($this->once())
                ->method('activeLanguages')
                ->will($this->returnValue($languages));
        
        $blockManager = new BlockManagerLanguagesMenu($this->container);
        $expectedResult = array(
            'RenderView' => array(
                'view' => 'RedKiteCmsBaseBlocksBundle:Content:LanguagesMenu/languages_menu.html.twig',
                'options' => array(
                    'languages' => $expectedLanguages,
                    'block_manager' => $blockManager,
                ),
            )
        );
        
        
        $blockManager->set($block);       
        $this->assertEquals($expectedResult, $blockManager->getHtml());
    }
    
    public function testEditorParameters()
    {
        $formFactory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $formFactory->expects($this->at(0))
                    ->method('create')
                    ->will($this->returnValue($this->initForm()))
        ;
                
        $this->container->expects($this->at(6))
                        ->method('get')
                        ->with('form.factory')
                        ->will($this->returnValue($formFactory))
        ;
        
        $block = $this->initBlock('{"imagesFolder":"20x15","languages":{"en":{"country":"/bundles/navigationmenu/20x15/uk.png","url":"#"},"fr":{"country":"/bundles/navigationmenu/20x15/fr.png","url":"#"}}}');
        $blockManager = new BlockManagerLanguagesMenu($this->container);
        $blockManager->set($block);
        $result = $blockManager->editorParameters();
        $this->assertEquals('RedKiteCmsBaseBlocksBundle:Editor:LanguagesMenu/editor.html.twig', $result["template"]);
    }
    
    /**
     * @dataProvider editValuesProvider
     */
    public function testEditValues($values, $expectedResult)
    {
        $this->initPageTree();
        $blockManager = new BlockManagerLanguagesMenuTester($this->container);
        
        $this->assertEquals($expectedResult, $blockManager->updateSavedLanguagesTester($values));
    }
    
    public function languagesProvider()
    {
        return array(
            // country is not detected for "en" language
            array(
                'en',
                '{"imagesFolder":"20x15","languages":{"en":{"country":"","url":"#"}}}',
            ),
            // country is detected for "fr" language
            array(
                'fr',
                '{"imagesFolder":"20x15","languages":{"fr":{"country":"\/bundles\/navigationmenu\/20x15\/fr.png","url":"#"}}}',
            ),
        );
    }
    
    public function htmlProvider()
    {
        return array(
            array(
                array(
                    'en',
                ),
                array(
                    'en' => array(
                        'country' => '',
                        'url' => '#',
                    ),
                ),
            ),
            array(
                array(
                    'fr',
                ),
                array(
                    'fr' => array(
                        'country' => '/bundles/navigationmenu/20x15/fr.png',
                        'url' => '#',
                    ),
                ),
            ),
            array(
                array(
                    'en',
                    'fr',
                ),
                array(
                    'en' => array(
                        'country' => '',
                        'url' => '#',
                    ),
                    'fr' => array(
                        'country' => '/bundles/navigationmenu/20x15/fr.png',
                        'url' => '#',
                    ),
                ),
            ),
            array(
                array(
                    'en',
                    'fr',
                ),
                array(
                    'en' => array(
                        'country' => '/bundles/navigationmenu/20x15/uk.png',
                        'url' => '#',
                    ),
                    'fr' => array(
                        'country' => '/bundles/navigationmenu/20x15/fr.png',
                        'url' => '#',
                    ),
                ),
                $this->initBlock('{"imagesFolder":"20x15","languages":{"en":{"country":"/bundles/navigationmenu/20x15/uk.png","url":"#"},"fr":{"country":"/bundles/navigationmenu/20x15/fr.png","url":"#"}}}'),
            ),
            array(
                array(
                    'en',
                    'fr',
                ),
                array(
                    'en' => array(
                        'country' => '/bundles/navigationmenu/40x30/uk.png',
                        'url' => '#',
                    ),
                    'fr' => array(
                        'country' => '/bundles/navigationmenu/40x30/fr.png',
                        'url' => '#',
                    ),
                ),
                $this->initBlock('{"imagesFolder":"40x30","languages":{"en":{"country":"/bundles/navigationmenu/40x30/uk.png","url":"#"},"fr":{"country":"/bundles/navigationmenu/40x30/fr.png","url":"#"}}}'),
            ),
        );
    }
    
    public function editValuesProvider()
    {
        return array(
            array(
                array(
                    'Content' => 'al_json_block[flags_directories]=20x15&al_json_block[en]=GB&al_json_block[fr]=FR',
                ),
                array(
                    'Content' => '{"imagesFolder":"20x15","languages":{"en":{"country":"\/bundles\/navigationmenu\/20x15\/gb.png","url":"#"},"fr":{"country":"\/bundles\/navigationmenu\/20x15\/fr.png","url":"#"}}}'
                ),
            ),
            array(
                array(
                    'Content' => 'al_json_block[flags_directories]=40x30&al_json_block[en]=GB&al_json_block[fr]=FR',
                ),
                array(
                    'Content' => '{"imagesFolder":"40x30","languages":{"en":{"country":"\/bundles\/navigationmenu\/40x30\/gb.png","url":"#"},"fr":{"country":"\/bundles\/navigationmenu\/40x30\/fr.png","url":"#"}}}'
                ),
            ),
        );
    }
    
    protected function initForm()
    {
        $form = $this->getMockBuilder('Symfony\Component\Form\Form')
                    ->disableOriginalConstructor()
                    ->getMock();
        $form->expects($this->once())
            ->method('createView')
        ;
        
        return $form;
    }
    
    protected function initBlock($content)
    {
        $block = $this->getMock('RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Model\Block');
        $block->expects($this->once())
            ->method('getContent')
            ->will($this->returnValue($content));
        
        return $block;
    }

    protected function initLanguage($value = 'en')
    {
        $language = $this->getMock('RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Model\Language');
        $language->expects($this->once())
            ->method('getLanguageName')
            ->will($this->returnValue($value));
        
        return $language;
    }
    
    protected function initPageTree()
    {
        $page = $this->getMock('RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Model\Page');
        $pageTree = $this->getMockBuilder('\RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\PageTree\PageTree')
                                    ->disableOriginalConstructor()
                                    ->getMock();
        $pageTree->expects($this->any())
            ->method('getPage')
            ->will($this->returnValue($page));
        
        $this->container->expects($this->at(6))
            ->method('get')
            ->with('red_kite_cms.page_tree')
            ->will($this->returnValue($pageTree));
            
        return $pageTree;
    }
}