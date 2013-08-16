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

namespace RedKiteCms\Block\RedKiteCmsBaseBlocksBundle\Tests\Unit\Core\Block\Menu;

use RedKiteLabs\RedKiteCmsBundle\Tests\Unit\Core\Content\Block\Base\AlBlockManagerContainerBase;

/**
 * BaseBlockManagerMenu
 *
 * @author RedKite Labs <info@redkite-labs.com>
 */
abstract class BaseBlockManagerMenu extends AlBlockManagerContainerBase
{
    protected $blocksTemplate;
    
    abstract protected function getBlockManager();
            
    public function testDefaultValue()
    {
        $expectedValue = array(
            'Content' => '
            {
                "0": {
                    "blockType" : "Link"
                },
                "1": {
                    "blockType" : "Link"
                }
            }'
        );

        $this->initContainer();
        $blockManager = $this->getBlockManager(); 
        $this->assertEquals($expectedValue, $blockManager->getDefaultValue());
    }
    
    public function testHtmlViewOutput()
    {
        $blockContent = 
            '{
                "0": {
                    "blockType" : "Link"
                },
                "1": {
                    "blockType" : "Link"
                }
            }';

        $this->initContainer();
        $block = $this->initBlock($blockContent);
        $blockManager = $this->getBlockManager();
        $blockManager->set($block);
        
        $expectedResult = array('RenderView' => array(
            'view' => $this->blocksTemplate,
            'options' => array(
                'items' => array (
                    array(
                        "blockType" => "Link"
                    ),
                    array(
                        "blockType" => "Link"
                    ),
                ),
                'block_manager' => $blockManager
            ),
        ));
        
        $this->assertEquals($expectedResult, $blockManager->getHtml());
    }
    
    protected function initContainer()
    {
        $repository = $this->getMock('RedKiteLabs\RedKiteCmsBundle\Core\Repository\Factory\AlFactoryRepositoryInterface');
        $repository->expects($this->at(0))
              ->method('createRepository')
              ->with('Block')
        ;
        
        $this->container->expects($this->at(0))
                        ->method('get')
                        ->with('red_kite_cms.events_handler')
                        ->will($this->returnValue($this->eventsHandler))
        ;
        
        $this->container->expects($this->at(1))
                      ->method('get')
                      ->will($this->returnValue($repository))
        ;
    }
    
    protected function initBlockManager($block)
    {
        $this->blockRepository = $this->getMockBuilder('RedKiteLabs\RedKiteCmsBundle\Core\Repository\Propel\AlBlockRepositoryPropel')
                                    ->disableOriginalConstructor()
                                    ->getMock();
        
        $this->blockRepository->expects($this->once())
                ->method('setRepositoryObject')
                ->with($block)
                ->will($this->returnSelf());
        
        $this->blockRepository->expects($this->once())
            ->method('startTransaction');

        $this->blockRepository->expects($this->once())
            ->method('commit');

        $this->blockRepository->expects($this->never())
            ->method('rollback');

        $this->blockRepository->expects($this->once())
                ->method('save')
                ->will($this->returnValue(true));
        
        $this->factoryRepository = $this->getMock('RedKiteLabs\RedKiteCmsBundle\Core\Repository\Factory\AlFactoryRepositoryInterface');
        $this->factoryRepository->expects($this->any())
            ->method('createRepository')
            ->will($this->returnValue($this->blockRepository));
        
        $this->initContainer();
        $event = $this->getMock('RedKiteLabs\RedKiteCmsBundle\Core\Event\Content\Block\BeforeBlockEditingEvent');
        $this->setUpEventsHandler($event, 2);
    }
    
    private function initBlock($value)
    {
        $block = $this->getMock('RedKiteLabs\RedKiteCmsBundle\Model\AlBlock');
        $block->expects($this->any())
              ->method('getContent')
              ->will($this->returnValue($value));
        
        $block->expects($this->any())
              ->method('getId')
              ->will($this->returnValue(2));

        return $block;
    }
}
