<?php

/**
 * @author Mygento Team
 * @copyright 2014-2020 Mygento (https://www.mygento.ru)
 * @package Mygento_Base
 */

namespace Mygento\Base\Test\Unit;

class DiscountMarkingItemsTest extends DiscountGeneralTestCase
{
    /**
     * @var array
     */
    private $markings = [];

    /**
     * Attention! Order of items in array is important!
     * @dataProvider dataProviderOrdersForCheckCalculation
     * @param mixed $order
     * @param mixed $expectedArray
     */
    public function testCalculation($order, $expectedArray)
    {
        parent::testCalculation($order, $expectedArray);

        $order->setShippingDescription('test_shipping');
        $this->assertTrue(method_exists($this->discountHelper, 'getRecalculated'));

        $recalculatedData = $this->discountHelper->getRecalculated($order, 'vat20', '', '', 'marking', 'marking_list');

        $this->assertEquals($recalculatedData['sum'], $expectedArray['sum'], 'Total sum failed');
        $this->assertEquals($recalculatedData['origGrandTotal'], $expectedArray['origGrandTotal']);

        $this->assertArrayHasKey('items', $recalculatedData);

        $recalcItems = array_values($recalculatedData['items']);
        $recalcExpectedItems = array_values($expectedArray['items']);

        $expectedQty = 0;

        foreach ($recalcExpectedItems as $expectedItem) {
            $expectedQty += $expectedItem['quantity'];
        }

        $recalcQty = 0;
        foreach ($recalcItems as $index => $recalcItem) {
            if ($recalcItem['name'] !== 'test_shipping') {
                $this->assertContains('SOME_MARK_', $recalcItem['marking'], 'Marking of item failed');
            }

            $this->assertEquals(1, $recalcItem['quantity']);
            $this->assertEquals($recalcItem['sum'], $recalcItem['price']);
            $recalcQty += $recalcItem['quantity'];
        }

        $this->assertEquals($expectedQty, $recalcQty, 'Items qty is incorrect');
    }

    /**
    * @param float $rowTotalInclTax
    * @param float $priceInclTax
    * @param float $discountAmount
    * @param int $qty
    * @param int $taxPercent
    * @param float|int $taxAmount
    * @param bool $marking
    * @param array $markingList
    * @return \Mygento\Base\Test\OrderItemMock
    */
    public function getItem(
        $rowTotalInclTax,
        $priceInclTax,
        $discountAmount,
        $qty = 1,
        $taxPercent = 0,
        $taxAmount = 0
    ) {
        static $id = 100500;
        $id++;

        $name = $this->getRandomString(8);

        if (empty($this->markings)) {
            for ($i = 1; $i < 1000; $i++) {
                $this->markings[] = 'SOME_MARK_' . $i;
            }
        }

        $markingList = $this->markings;

        $item = $this->getObjectManager()->getObject(
            \Mygento\Base\Test\OrderItemMock::class
        );

        $item->setData('id', $id);
        $item->setData('row_total_incl_tax', $rowTotalInclTax);
        $item->setData('price_incl_tax', $priceInclTax);
        $item->setData('discount_amount', $discountAmount);
        $item->setData('qty', $qty);
        $item->setData('name', $name);
        $item->setData('tax_percent', $taxPercent);
        $item->setData('tax_amount', $taxAmount);
        $item->setData('marking', true);
        $item->setData('marking_list', $markingList);

        return $item;
    }

    /** Test splitting item mechanism
     *
     * @dataProvider dataProviderItemsForMarking
     * @param mixed $item
     * @param mixed $expectedArray
     */
    public function testProcessedItem($item, $expectedArray)
    {
        $discountHelper = $this->getDiscountHelperInstance();
        $discountHelper->setIsSplitItemsAllowed(true);

        $dHelper = new \ReflectionClass($discountHelper);

        $markingAttributeCodeAttr = $dHelper->getProperty('markingAttributeCode');
        $markingAttributeCodeAttr->setAccessible(true);
        $markingAttributeCodeAttr->setValue($discountHelper, \Mygento\Base\Helper\Discount::NAME_MARKING);

        $markingAttributeCodeListAttr = $dHelper->getProperty('markingListAttributeCode');
        $markingAttributeCodeListAttr->setAccessible(true);
        $markingAttributeCodeListAttr->setValue($discountHelper, \Mygento\Base\Helper\Discount::NAME_MARKING_LIST);

        $getProcessedItem = $dHelper->getMethod('getProcessedItem');
        $getProcessedItem->setAccessible(true);

        $split = $getProcessedItem->invoke($discountHelper, $item);

        $this->assertEquals(count($split), count($expectedArray), 'Item was not splitted correctly!');

        $i = 0;
        foreach ($split as $item) {
            $this->assertEquals($expectedArray[$i]['price'], $item['price'], 'Price of item failed');
            $this->assertEquals($expectedArray[$i]['quantity'], $item['quantity']);
            $this->assertEquals($expectedArray[$i]['sum'], $item['sum'], 'Sum of item failed');
            $this->assertEquals($expectedArray[$i]['marking'], $item['marking'], 'Marking of item failed');

            $i++;
        }
    }

    /**
     * @dataProvider dataProviderItemsMarkItems
     * @param mixed $item
     * @param mixed $expectedArray
     */
    public function testMarkItems($item, $expectedArray)
    {
        $discountHelper = $this->getDiscountHelperInstance();

        $dHelper = new \ReflectionClass($discountHelper);

        $getProcessedItem = $dHelper->getMethod('markItems');
        $getProcessedItem->setAccessible(true);

        $marked = $getProcessedItem->invokeArgs($discountHelper, [
            'items' => $item[0],
            'marks' => $item[1]
        ]);

        $i = 0;
        foreach ($marked as $result) {
            $this->assertEquals($expectedArray[$i]['marking'], $result['marking'], 'Marking of item failed');
            $i++;
        }
    }

    /**
     * @return array
     * @SuppressWarnings(PHPMD)
     */
    public function dataProviderItemsMarkItems()
    {
        return [
            '#case 1. 10 элементов с 10 маркировками' => [
                [
                    [[], [], [], [], [], [], [], [], [], []],
                    [
                        'SOME_MARK_1', 'SOME_MARK_2', 'SOME_MARK_3', 'SOME_MARK_4', 'SOME_MARK_5',
                        'SOME_MARK_6', 'SOME_MARK_7', 'SOME_MARK_8', 'SOME_MARK_9', 'SOME_MARK_10'
                    ]
                ],
                [
                    [
                        'marking' => 'SOME_MARK_1'
                    ],
                    [
                        'marking' => 'SOME_MARK_2'
                    ],
                    [
                        'marking' => 'SOME_MARK_3'
                    ],
                    [
                        'marking' => 'SOME_MARK_4'
                    ],
                    [
                        'marking' => 'SOME_MARK_5'
                    ],
                    [
                        'marking' => 'SOME_MARK_6'
                    ],
                    [
                        'marking' => 'SOME_MARK_7'
                    ],
                    [
                        'marking' => 'SOME_MARK_8'
                    ],
                    [
                        'marking' => 'SOME_MARK_9'
                    ],
                    [
                        'marking' => 'SOME_MARK_10'
                    ]
                ]
            ],
            '#case 2. 3 элемента, 2 маркировки' => [
                [
                    [[], [], []], ['SOME_MARK_1', 'SOME_MARK_2']
                ],
                [
                    [
                        'marking' => 'SOME_MARK_1'
                    ],
                    [
                        'marking' => 'SOME_MARK_2'
                    ],
                    [
                        'marking' => null
                    ]
                ]
            ],
        ];
    }

    /**
     * @dataProvider dataProviderItemsPacktems
     * @param mixed $item
     * @param mixed $expectedArray
     */
    public function testPackItems($item, $expectedArray)
    {
        $discountHelper = $this->getDiscountHelperInstance();

        $dHelper = new \ReflectionClass($discountHelper);

        $getProcessedItem = $dHelper->getMethod('packItems');
        $getProcessedItem->setAccessible(true);

        $packed = $getProcessedItem->invokeArgs($discountHelper, $item);
        $this->assertEquals(array_keys($expectedArray), array_keys($packed), 'Packing of item failed');
    }

    /**
     * @return array
     * @SuppressWarnings(PHPMD)
     */
    public function dataProviderItemsPacktems()
    {
        $final = [];

        $item1 = $this->getItem(0, 0, 0, 1);
        $item1->setData(\Mygento\Base\Helper\Discount::NAME_ROW_DIFF, 2);
        $item1->setData(\Mygento\Base\Helper\Discount::NAME_UNIT_PRICE, 10.59);

        $final['#case 1. qty = 1.'] = [
            [
                'item' => $item1,
                'items' => [[]]
            ],
            [
                $item1->getId() => []
            ]
        ];

        $item2 = $this->getItem(0, 0, 0, 3);
        $item2->setData(\Mygento\Base\Helper\Discount::NAME_ROW_DIFF, 2);
        $item2->setData(\Mygento\Base\Helper\Discount::NAME_UNIT_PRICE, 10.59);

        $final['#case 2. qty = 3.'] = [
            [
                'item' => $item1,
                'items' => [[], [], []]
            ],
            [
                $item1->getId() . '_1' => [],
                $item1->getId() . '_2' => [],
                $item1->getId() . '_3' => []
            ]
        ];

        return $final;
    }

    /**
     * @return array
     * @SuppressWarnings(PHPMD)
     */
    public function dataProviderItemsForMarking()
    {
        $final = [];

        // #1 rowDiff = 2 kop. qty = 3. qtyUpdate = 3
        $item = $this->getItem(0, 0, 0, 3);
        $item->setData(\Mygento\Base\Helper\Discount::NAME_ROW_DIFF, 2);
        $item->setData(\Mygento\Base\Helper\Discount::NAME_UNIT_PRICE, 10.59);
        $item->setData(\Mygento\Base\Helper\Discount::NAME_MARKING, true);
        $item->setData(\Mygento\Base\Helper\Discount::NAME_MARKING_LIST, [
            'SOME_MARK_1', 'SOME_MARK_2', 'SOME_MARK_3'
        ]);

        $expected = [
            [
                'price' => 10.59,
                'quantity' => 1,
                'sum' => 10.59,
                'tax' => null,
                'marking' => 'SOME_MARK_1'
            ],
            [
                'price' => 10.6,
                'quantity' => 1,
                'sum' => 10.6,
                'tax' => null,
                'marking' => 'SOME_MARK_2'
            ],
            [
                'price' => 10.6,
                'quantity' => 1,
                'sum' => 10.6,
                'tax' => null,
                'marking' => 'SOME_MARK_3'
            ],
        ];
        $final['#case 1. 2 копейки распределить по 3м товарам.'] = [$item, $expected];

        return $final;
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected static function getExpected()
    {
        $actualData[parent::TEST_CASE_NAME_1] = [
            'sum' => 12069.30,
            'origGrandTotal' => 12069.30,
            'items' => [
                152 => [
                    'price' => 11691,
                    'quantity' => 1,
                    'sum' => 11691,
                ],
                153 => [
                    'price' => 378.30,
                    'quantity' => 1,
                    'sum' => 378.30,
                ],
                154 => [
                    'price' => 0,
                    'quantity' => 1,
                    'sum' => 0,
                ],
                'shipping' => [
                    'price' => 0.00,
                    'quantity' => 1,
                    'sum' => 0,
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_2] = [
            'sum' => 5086.19,
            'origGrandTotal' => 9373.19,
            'items' => [
                152 => [
                    'price' => 5054.4,
                    'quantity' => 1,
                    'sum' => 5054.4,
                ],
                '154_1' => [
                    'price' => 10.59,
                    'quantity' => 1,
                    'sum' => 10.59,
                ],
                '153_2' => [
                    'price' => 10.6,
                    'quantity' => 2,
                    'sum' => 21.2,
                ],
                'shipping' => [
                    'price' => 4287.00,
                    'quantity' => 1,
                    'sum' => 4287.00,
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_3] = [
            'sum' => 5086.19,
            'origGrandTotal' => 5106.19,
            'items' => [
                152 => [
                    'price' => 5015.28,
                    'quantity' => 1,
                    'sum' => 5015.28,
                ],
                '153_1' => [
                    'price' => 23.63,
                    'quantity' => 1,
                    'sum' => 23.63,
                ],
                '153_2' => [
                    'price' => 23.64,
                    'quantity' => 2,
                    'sum' => 47.28,
                ],
                'shipping' => [
                    'price' => 20.00,
                    'quantity' => 1,
                    'sum' => 20.00,
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_4] = [
            'sum' => 5000.86,
            'origGrandTotal' => 5200.86,
            'items' => [
                152 => [
                    'price' => 500.41,
                    'quantity' => 2,
                    'sum' => 1000.82,
                ],
                153 => [
                    'price' => 1000.01,
                    'quantity' => 4,
                    'sum' => 4000.04,
                ],
                'shipping' => [
                    'price' => 200,
                    'quantity' => 1,
                    'sum' => 200,
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_5] = [
            'sum' => 202.1,
            'origGrandTotal' => 202.1,
            'items' => [
                '152_1' => [
                    'price' => 33.66,
                    'quantity' => 1,
                    'sum' => 33.66,
                ],
                '152_2' => [
                    'price' => 33.67,
                    'quantity' => 2,
                    'sum' => 67.34,
                ],
                '153_1' => [
                    'price' => 25.27,
                    'quantity' => 2,
                    'sum' => 50.54,
                ],
                '153_2' => [
                    'price' => 25.28,
                    'quantity' => 2,
                    'sum' => 50.56,
                ],
                'shipping' => [
                    'price' => 0.0,
                    'quantity' => 1,
                    'sum' => 0.00,
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_6] = [
            'sum' => 702.1,
            'origGrandTotal' => 702.1,
            'items' => [
                '152_1' => [
                    'price' => 33.66,
                    'quantity' => 1,
                    'sum' => 33.66,
                ],
                '152_2' => [
                    'price' => 33.67,
                    'quantity' => 2,
                    'sum' => 67.34,
                ],
                '153_1' => [
                    'price' => 25.27,
                    'quantity' => 2,
                    'sum' => 50.54,
                ],
                '153_2' => [
                    'price' => 25.28,
                    'quantity' => 2,
                    'sum' => 50.56,
                ],
                154 => [
                    'price' => 100,
                    'quantity' => 5,
                    'sum' => 500,
                ],
                'shipping' => [
                    'price' => 0.00,
                    'quantity' => 1,
                    'sum' => 0.00,
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_7] = [
            'sum' => 11691.0,
            'origGrandTotal' => 11691.0,
            'items' => [
                152 => [
                    'price' => 11691.0,
                    'quantity' => 1,
                    'sum' => 11691.0,
                ],
                153 => [
                    'price' => 0.0,
                    'quantity' => 1,
                    'sum' => 0.0,
                ],
                'shipping' => [
                    'price' => 0.00,
                    'quantity' => 1,
                    'sum' => 0.00,
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_8] = [
            'sum' => 11611.0,
            'origGrandTotal' => 11611.0,
            'items' => [
                152 => [
                    'price' => 11591.15,
                    'quantity' => 1,
                    'sum' => 11591.15,
                ],
                153 => [
                    'price' => 19.85,
                    'quantity' => 1,
                    'sum' => 19.85,
                ],
                'shipping' => [
                    'price' => 0.00,
                    'quantity' => 1,
                    'sum' => 0.00,
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_9] = [
            'sum' => 12890.0,
            'origGrandTotal' => 12890.0,
            'items' => [
                152 => [
                    'price' => 12890.0,
                    'quantity' => 1,
                    'sum' => 12890.0,
                ],
                'shipping' => [
                    'price' => 0.00,
                    'quantity' => 1,
                    'sum' => 0.00,
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_10] = [
            'sum' => 12909.99,
            'origGrandTotal' => 12909.99,
            'items' => [
                152 => [
                    'price' => 12890.14,
                    'quantity' => 1,
                    'sum' => 12890.14,
                ],
                153 => [
                    'price' => 19.85,
                    'quantity' => 1,
                    'sum' => 19.85,
                ],
                'shipping' => [
                    'price' => 0.00,
                    'quantity' => 1,
                    'sum' => 0.00,
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_11] = [
            'sum' => 32130.01,
            'origGrandTotal' => 32130.01,
            'items' => [
                0 => [
                    'price' => 19990,
                    'quantity' => 1,
                    'sum' => 19990,
                    'tax' => 'vat20',
                ],
                1 => [
                    'price' => 19,
                    'quantity' => 500,
                    'sum' => 9500,
                    'tax' => 'vat20',
                ],
                2 => [
                    'price' => 1000.01,
                    'quantity' => 1,
                    'sum' => 1000.01,
                    'tax' => 'vat20',
                ],
                3 => [
                    'price' => 410,
                    'quantity' => 4,
                    'sum' => 1640,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'price' => 0,
                    'quantity' => 1,
                    'sum' => 0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_12] = [
            'sum' => 13189.99,
            'origGrandTotal' => 13189.99,
            'items' => [
                0 => [
                    'price' => 7989.99,
                    'name' => 'HbcIyFpc',
                    'quantity' => 1,
                    'sum' => 7989.99,
                    'tax' => 'vat20',
                ],
                '100527_1' => [
                    'price' => 18.35,
                    'name' => 'iVZQ2iMO',
                    'quantity' => 28,
                    'sum' => 513.8,
                    'tax' => 'vat20',
                ],
                '100527_2' => [
                    'price' => 18.36,
                    'name' => 'iVZQ2iMO',
                    'quantity' => 12,
                    'sum' => 220.32,
                    'tax' => 'vat20',
                ],
                '100528_1' => [
                    'price' => 18.35,
                    'name' => 'CVUohjpK',
                    'quantity' => 21,
                    'sum' => 385.35,
                    'tax' => 'vat20',
                ],
                '100528_2' => [
                    'price' => 18.36,
                    'name' => 'CVUohjpK',
                    'quantity' => 9,
                    'sum' => 165.24,
                    'tax' => 'vat20',
                ],
                '100529_1' => [
                    'price' => 14.78,
                    'name' => '3JFWNpUY',
                    'quantity' => 23,
                    'sum' => 339.94,
                    'tax' => 'vat20',
                ],
                '100529_2' => [
                    'price' => 14.79,
                    'name' => '3JFWNpUY',
                    'quantity' => 17,
                    'sum' => 251.43,
                    'tax' => 'vat20',
                ],
                '100530_1' => [
                    'price' => 14.78,
                    'name' => 'eLjly6un',
                    'quantity' => 28,
                    'sum' => 413.84,
                    'tax' => 'vat20',
                ],
                '100530_2' => [
                    'price' => 14.79,
                    'name' => 'eLjly6un',
                    'quantity' => 22,
                    'sum' => 325.38,
                    'tax' => 'vat20',
                ],
                '100531_1' => [
                    'price' => 18.35,
                    'name' => 'nZ0KslHN',
                    'quantity' => 21,
                    'sum' => 385.35,
                    'tax' => 'vat20',
                ],
                '100531_2' => [
                    'price' => 18.36,
                    'name' => 'nZ0KslHN',
                    'quantity' => 9,
                    'sum' => 165.24,
                    'tax' => 'vat20',
                ],
                '100532_1' => [
                    'price' => 18.35,
                    'name' => 'xk0eWAiC',
                    'quantity' => 7,
                    'sum' => 128.45,
                    'tax' => 'vat20',
                ],
                '100532_2' => [
                    'price' => 18.36,
                    'name' => 'xk0eWAiC',
                    'quantity' => 3,
                    'sum' => 55.08,
                    'tax' => 'vat20',
                ],
                '100533_1' => [
                    'price' => 18.35,
                    'name' => 'QZc84oGJ',
                    'quantity' => 35,
                    'sum' => 642.25,
                    'tax' => 'vat20',
                ],
                '100533_2' => [
                    'price' => 18.36,
                    'name' => 'QZc84oGJ',
                    'quantity' => 15,
                    'sum' => 275.4,
                    'tax' => 'vat20',
                ],
                '100534_1' => [
                    'price' => 16.82,
                    'name' => 'EZ45M8YX',
                    'quantity' => 6,
                    'sum' => 100.92,
                    'tax' => 'vat20',
                ],
                '100534_2' => [
                    'price' => 16.83,
                    'name' => 'EZ45M8YX',
                    'quantity' => 4,
                    'sum' => 67.32,
                    'tax' => 'vat20',
                ],
                '100535_1' => [
                    'price' => 18.35,
                    'name' => '1fSGTfUL',
                    'quantity' => 14,
                    'sum' => 256.9,
                    'tax' => 'vat20',
                ],
                '100535_2' => [
                    'price' => 18.36,
                    'name' => '1fSGTfUL',
                    'quantity' => 6,
                    'sum' => 110.16,
                    'tax' => 'vat20',
                ],
                '100536_1' => [
                    'price' => 19.88,
                    'name' => 'KK1Iub5Q',
                    'quantity' => 17,
                    'sum' => 337.96,
                    'tax' => 'vat20',
                ],
                '100536_2' => [
                    'price' => 19.89,
                    'name' => 'KK1Iub5Q',
                    'quantity' => 3,
                    'sum' => 59.67,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'name' => '',
                    'price' => 0,
                    'quantity' => 1,
                    'sum' => 0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_13] = [
            'sum' => 5199.99,
            'origGrandTotal' => 5199.99,
            'items' => [
                '100537_1' => [
                    'price' => 18.35,
                    'name' => 'QasOwBxx',
                    'quantity' => 29,
                    'sum' => 532.15,
                    'tax' => 'vat20',
                ],
                '100537_2' => [
                    'price' => 18.36,
                    'name' => 'QasOwBxx',
                    'quantity' => 11,
                    'sum' => 201.96,
                    'tax' => 'vat20',
                ],
                '100538_1' => [
                    'price' => 18.35,
                    'name' => 'i3q2Pqi2',
                    'quantity' => 21,
                    'sum' => 385.35,
                    'tax' => 'vat20',
                ],
                '100538_2' => [
                    'price' => 18.36,
                    'name' => 'i3q2Pqi2',
                    'quantity' => 9,
                    'sum' => 165.24,
                    'tax' => 'vat20',
                ],
                '100539_1' => [
                    'price' => 14.78,
                    'name' => '4yAnOQ9Q',
                    'quantity' => 23,
                    'sum' => 339.94,
                    'tax' => 'vat20',
                ],
                '100539_2' => [
                    'price' => 14.79,
                    'name' => '4yAnOQ9Q',
                    'quantity' => 17,
                    'sum' => 251.43,
                    'tax' => 'vat20',
                ],
                '100540_1' => [
                    'price' => 14.78,
                    'name' => 'BpzRVt8O',
                    'quantity' => 28,
                    'sum' => 413.84,
                    'tax' => 'vat20',
                ],
                '100540_2' => [
                    'price' => 14.79,
                    'name' => 'BpzRVt8O',
                    'quantity' => 22,
                    'sum' => 325.38,
                    'tax' => 'vat20',
                ],
                '100541_1' => [
                    'price' => 18.35,
                    'name' => 'bOsve0El',
                    'quantity' => 21,
                    'sum' => 385.35,
                    'tax' => 'vat20',
                ],
                '100541_2' => [
                    'price' => 18.36,
                    'name' => 'bOsve0El',
                    'quantity' => 9,
                    'sum' => 165.24,
                    'tax' => 'vat20',
                ],
                '100542_1' => [
                    'price' => 18.35,
                    'name' => 'VD4fh7ow',
                    'quantity' => 7,
                    'sum' => 128.45,
                    'tax' => 'vat20',
                ],
                '100542_2' => [
                    'price' => 18.36,
                    'name' => 'VD4fh7ow',
                    'quantity' => 3,
                    'sum' => 55.08,
                    'tax' => 'vat20',
                ],
                '100543_1' => [
                    'price' => 18.35,
                    'name' => '7rUyXogC',
                    'quantity' => 35,
                    'sum' => 642.25,
                    'tax' => 'vat20',
                ],
                '100543_2' => [
                    'price' => 18.36,
                    'name' => '7rUyXogC',
                    'quantity' => 15,
                    'sum' => 275.4,
                    'tax' => 'vat20',
                ],
                '100544_1' => [
                    'price' => 16.82,
                    'name' => '4Vjv1sDw',
                    'quantity' => 6,
                    'sum' => 100.92,
                    'tax' => 'vat20',
                ],
                '100544_2' => [
                    'price' => 16.83,
                    'name' => '4Vjv1sDw',
                    'quantity' => 4,
                    'sum' => 67.32,
                    'tax' => 'vat20',
                ],
                '100545_1' => [
                    'price' => 18.35,
                    'name' => 'I1v7ozqi',
                    'quantity' => 14,
                    'sum' => 256.9,
                    'tax' => 'vat20',
                ],
                '100545_2' => [
                    'price' => 18.36,
                    'name' => 'I1v7ozqi',
                    'quantity' => 6,
                    'sum' => 110.16,
                    'tax' => 'vat20',
                ],
                '100546_1' => [
                    'price' => 19.88,
                    'name' => 'vPZr7cV2',
                    'quantity' => 17,
                    'sum' => 337.96,
                    'tax' => 'vat20',
                ],
                '100546_2' => [
                    'price' => 19.89,
                    'name' => 'vPZr7cV2',
                    'quantity' => 3,
                    'sum' => 59.67,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'name' => '',
                    'price' => 0,
                    'quantity' => 1,
                    'sum' => 0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_14] = [
            'sum' => 13190.01,
            'origGrandTotal' => 13190.01,
            'items' => [
                0 => [
                    'price' => 7990.01,
                    'name' => 'BPIXObQh',
                    'quantity' => 1,
                    'sum' => 7990.01,
                    'tax' => 'vat20',
                ],
                '100548_1' => [
                    'price' => 18.35,
                    'name' => 'kXtzFQk9',
                    'quantity' => 28,
                    'sum' => 513.8,
                    'tax' => 'vat20',
                ],
                '100548_2' => [
                    'price' => 18.36,
                    'name' => 'kXtzFQk9',
                    'quantity' => 12,
                    'sum' => 220.32,
                    'tax' => 'vat20',
                ],
                '100549_1' => [
                    'price' => 18.35,
                    'name' => 'VjBZWKqC',
                    'quantity' => 21,
                    'sum' => 385.35,
                    'tax' => 'vat20',
                ],
                '100549_2' => [
                    'price' => 18.36,
                    'name' => 'VjBZWKqC',
                    'quantity' => 9,
                    'sum' => 165.24,
                    'tax' => 'vat20',
                ],
                '100550_1' => [
                    'price' => 14.78,
                    'name' => 'yOhCey5i',
                    'quantity' => 23,
                    'sum' => 339.94,
                    'tax' => 'vat20',
                ],
                '100550_2' => [
                    'price' => 14.79,
                    'name' => 'yOhCey5i',
                    'quantity' => 17,
                    'sum' => 251.43,
                    'tax' => 'vat20',
                ],
                '100551_1' => [
                    'price' => 14.78,
                    'name' => 'x744Y2VU',
                    'quantity' => 28,
                    'sum' => 413.84,
                    'tax' => 'vat20',
                ],
                '100551_2' => [
                    'price' => 14.79,
                    'name' => 'x744Y2VU',
                    'quantity' => 22,
                    'sum' => 325.38,
                    'tax' => 'vat20',
                ],
                '100552_1' => [
                    'price' => 18.35,
                    'name' => 'LNgNKsqq',
                    'quantity' => 21,
                    'sum' => 385.35,
                    'tax' => 'vat20',
                ],
                '100552_2' => [
                    'price' => 18.36,
                    'name' => 'LNgNKsqq',
                    'quantity' => 9,
                    'sum' => 165.24,
                    'tax' => 'vat20',
                ],
                '100553_1' => [
                    'price' => 18.35,
                    'name' => '4vrYuAmx',
                    'quantity' => 7,
                    'sum' => 128.45,
                    'tax' => 'vat20',
                ],
                '100553_2' => [
                    'price' => 18.36,
                    'name' => '4vrYuAmx',
                    'quantity' => 3,
                    'sum' => 55.08,
                    'tax' => 'vat20',
                ],
                '100554_1' => [
                    'price' => 18.35,
                    'name' => '5KCqwVCK',
                    'quantity' => 35,
                    'sum' => 642.25,
                    'tax' => 'vat20',
                ],
                '100554_2' => [
                    'price' => 18.36,
                    'name' => '5KCqwVCK',
                    'quantity' => 15,
                    'sum' => 275.4,
                    'tax' => 'vat20',
                ],
                '100555_1' => [
                    'price' => 16.82,
                    'name' => 'SiJ3Zm9y',
                    'quantity' => 6,
                    'sum' => 100.92,
                    'tax' => 'vat20',
                ],
                '100555_2' => [
                    'price' => 16.83,
                    'name' => 'SiJ3Zm9y',
                    'quantity' => 4,
                    'sum' => 67.32,
                    'tax' => 'vat20',
                ],
                '100556_1' => [
                    'price' => 18.35,
                    'name' => '2IqCgAK6',
                    'quantity' => 14,
                    'sum' => 256.9,
                    'tax' => 'vat20',
                ],
                '100556_2' => [
                    'price' => 18.36,
                    'name' => '2IqCgAK6',
                    'quantity' => 6,
                    'sum' => 110.16,
                    'tax' => 'vat20',
                ],
                '100557_1' => [
                    'price' => 19.88,
                    'name' => 'UDzfCEuJ',
                    'quantity' => 17,
                    'sum' => 337.96,
                    'tax' => 'vat20',
                ],
                '100557_2' => [
                    'price' => 19.89,
                    'name' => 'UDzfCEuJ',
                    'quantity' => 3,
                    'sum' => 59.67,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'name' => '',
                    'price' => 0,
                    'quantity' => 1,
                    'sum' => 0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_15] = [
            'sum' => 13189.69,
            'origGrandTotal' => 13189.69,
            'items' => [
                0 => [
                    'price' => 7989.96,
                    'name' => 'WK6xqA9P',
                    'quantity' => 1,
                    'sum' => 7989.96,
                    'tax' => 'vat20',
                ],
                '100559_1' => [
                    'price' => 18.35,
                    'name' => 'TGKkaZv4',
                    'quantity' => 32,
                    'sum' => 587.2,
                    'tax' => 'vat20',
                ],
                '100559_2' => [
                    'price' => 18.36,
                    'name' => 'TGKkaZv4',
                    'quantity' => 8,
                    'sum' => 146.88,
                    'tax' => 'vat20',
                ],
                '100560_1' => [
                    'price' => 18.35,
                    'name' => 'EixOXbqy',
                    'quantity' => 25,
                    'sum' => 458.75,
                    'tax' => 'vat20',
                ],
                '100560_2' => [
                    'price' => 18.36,
                    'name' => 'EixOXbqy',
                    'quantity' => 5,
                    'sum' => 91.8,
                    'tax' => 'vat20',
                ],
                '100561_1' => [
                    'price' => 14.78,
                    'name' => 'kCJIu3aM',
                    'quantity' => 27,
                    'sum' => 399.06,
                    'tax' => 'vat20',
                ],
                '100561_2' => [
                    'price' => 14.79,
                    'name' => 'kCJIu3aM',
                    'quantity' => 13,
                    'sum' => 192.27,
                    'tax' => 'vat20',
                ],
                '100562_1' => [
                    'price' => 14.78,
                    'name' => 'MdGYRsVO',
                    'quantity' => 31,
                    'sum' => 458.18,
                    'tax' => 'vat20',
                ],
                '100562_2' => [
                    'price' => 14.79,
                    'name' => 'MdGYRsVO',
                    'quantity' => 19,
                    'sum' => 281.01,
                    'tax' => 'vat20',
                ],
                '100563_1' => [
                    'price' => 18.35,
                    'name' => 'ttQ8ylbR',
                    'quantity' => 23,
                    'sum' => 422.05,
                    'tax' => 'vat20',
                ],
                '100563_2' => [
                    'price' => 18.36,
                    'name' => 'ttQ8ylbR',
                    'quantity' => 7,
                    'sum' => 128.52,
                    'tax' => 'vat20',
                ],
                '100564_1' => [
                    'price' => 18.35,
                    'name' => 'wWrAvSS9',
                    'quantity' => 9,
                    'sum' => 165.15,
                    'tax' => 'vat20',
                ],
                '100564_2' => [
                    'price' => 18.36,
                    'name' => 'wWrAvSS9',
                    'quantity' => 1,
                    'sum' => 18.36,
                    'tax' => 'vat20',
                ],
                '100565_1' => [
                    'price' => 18.35,
                    'name' => 'v415Myym',
                    'quantity' => 37,
                    'sum' => 678.95,
                    'tax' => 'vat20',
                ],
                '100565_2' => [
                    'price' => 18.36,
                    'name' => 'v415Myym',
                    'quantity' => 13,
                    'sum' => 238.68,
                    'tax' => 'vat20',
                ],
                '100566_1' => [
                    'price' => 16.82,
                    'name' => '2KkA1lAQ',
                    'quantity' => 8,
                    'sum' => 134.56,
                    'tax' => 'vat20',
                ],
                '100566_2' => [
                    'price' => 16.83,
                    'name' => '2KkA1lAQ',
                    'quantity' => 2,
                    'sum' => 33.66,
                    'tax' => 'vat20',
                ],
                '100567_1' => [
                    'price' => 18.35,
                    'name' => 'Mbg5nGZU',
                    'quantity' => 16,
                    'sum' => 293.6,
                    'tax' => 'vat20',
                ],
                '100567_2' => [
                    'price' => 18.36,
                    'name' => 'Mbg5nGZU',
                    'quantity' => 4,
                    'sum' => 73.44,
                    'tax' => 'vat20',
                ],
                '100568_1' => [
                    'price' => 19.88,
                    'name' => 'janvE9Ay',
                    'quantity' => 19,
                    'sum' => 377.72,
                    'tax' => 'vat20',
                ],
                '100568_2' => [
                    'price' => 19.89,
                    'name' => 'janvE9Ay',
                    'quantity' => 1,
                    'sum' => 19.89,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'name' => '',
                    'price' => 0,
                    'quantity' => 1,
                    'sum' => 0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_16] = [
            'sum' => 5190.01,
            'origGrandTotal' => 5190.01,
            'items' => [
                '100569_1' => [
                    'price' => 18.31,
                    'name' => 'aQQcXkLB',
                    'quantity' => 20,
                    'sum' => 366.2,
                    'tax' => 'vat20',
                ],
                '100569_2' => [
                    'price' => 18.32,
                    'name' => 'aQQcXkLB',
                    'quantity' => 20,
                    'sum' => 366.4,
                    'tax' => 'vat20',
                ],
                '100570_1' => [
                    'price' => 18.3,
                    'name' => 'pP4cXE4c',
                    'quantity' => 9,
                    'sum' => 164.7,
                    'tax' => 'vat20',
                ],
                '100570_2' => [
                    'price' => 18.31,
                    'name' => 'pP4cXE4c',
                    'quantity' => 21,
                    'sum' => 384.51,
                    'tax' => 'vat20',
                ],
                '100571_1' => [
                    'price' => 14.75,
                    'name' => 'NeU6lQRX',
                    'quantity' => 27,
                    'sum' => 398.25,
                    'tax' => 'vat20',
                ],
                '100571_2' => [
                    'price' => 14.76,
                    'name' => 'NeU6lQRX',
                    'quantity' => 13,
                    'sum' => 191.88,
                    'tax' => 'vat20',
                ],
                '100572_1' => [
                    'price' => 14.76,
                    'name' => 'aHX2WaxX',
                    'quantity' => 39,
                    'sum' => 575.64,
                    'tax' => 'vat20',
                ],
                '100572_2' => [
                    'price' => 14.77,
                    'name' => 'aHX2WaxX',
                    'quantity' => 11,
                    'sum' => 162.47,
                    'tax' => 'vat20',
                ],
                '100573_1' => [
                    'price' => 18.31,
                    'name' => 'fLjhVF9j',
                    'quantity' => 2,
                    'sum' => 36.62,
                    'tax' => 'vat20',
                ],
                '100573_2' => [
                    'price' => 18.32,
                    'name' => 'fLjhVF9j',
                    'quantity' => 28,
                    'sum' => 512.96,
                    'tax' => 'vat20',
                ],
                '100574_1' => [
                    'price' => 18.26,
                    'name' => 'xuu28xie',
                    'quantity' => 8,
                    'sum' => 146.08,
                    'tax' => 'vat20',
                ],
                '100574_2' => [
                    'price' => 18.27,
                    'name' => 'xuu28xie',
                    'quantity' => 2,
                    'sum' => 36.54,
                    'tax' => 'vat20',
                ],
                '100575_1' => [
                    'price' => 18.33,
                    'name' => 'XjE4SYr8',
                    'quantity' => 16,
                    'sum' => 293.28,
                    'tax' => 'vat20',
                ],
                '100575_2' => [
                    'price' => 18.34,
                    'name' => 'XjE4SYr8',
                    'quantity' => 34,
                    'sum' => 623.56,
                    'tax' => 'vat20',
                ],
                '100576_1' => [
                    'price' => 16.74,
                    'name' => 'ZW6A11yF',
                    'quantity' => 1,
                    'sum' => 16.74,
                    'tax' => 'vat20',
                ],
                '100576_2' => [
                    'price' => 16.75,
                    'name' => 'ZW6A11yF',
                    'quantity' => 9,
                    'sum' => 150.75,
                    'tax' => 'vat20',
                ],
                '100577_1' => [
                    'price' => 18.31,
                    'name' => 'UOn0RlkO',
                    'quantity' => 1,
                    'sum' => 18.31,
                    'tax' => 'vat20',
                ],
                '100577_2' => [
                    'price' => 18.32,
                    'name' => 'UOn0RlkO',
                    'quantity' => 19,
                    'sum' => 348.08,
                    'tax' => 'vat20',
                ],
                '100578_1' => [
                    'price' => 19.85,
                    'name' => 'ZFV54mXu',
                    'quantity' => 16,
                    'sum' => 317.6,
                    'tax' => 'vat20',
                ],
                '100578_2' => [
                    'price' => 19.86,
                    'name' => 'ZFV54mXu',
                    'quantity' => 4,
                    'sum' => 79.44,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'name' => '',
                    'price' => 0,
                    'quantity' => 1,
                    'sum' => 0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_17] = [
            'sum' => 7989.99,
            'origGrandTotal' => 7989.99,
            'items' => [
                0 => [
                    'price' => 0,
                    'name' => 'BNjkAE3U',
                    'quantity' => 100,
                    'sum' => 0,
                    'tax' => 'vat20',
                ],
                1 => [
                    'price' => 7989.99,
                    'name' => 'hUSwdaHQ',
                    'quantity' => 1,
                    'sum' => 7989.99,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'name' => '',
                    'price' => 0,
                    'quantity' => 1,
                    'sum' => 0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_18] = [
            'sum' => 1500.0,
            'origGrandTotal' => 1500.0,
            'items' => [
                0 => [
                    'price' => 1000.0,
                    'name' => 'WQPsnwpZ',
                    'quantity' => 1.0,
                    'sum' => 1000.0,
                    'tax' => 'vat20',
                ],
                1 => [
                    'price' => 500.0,
                    'name' => 'xN7k7d5b',
                    'quantity' => 1.0,
                    'sum' => 500.0,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'name' => '',
                    'price' => 0.0,
                    'quantity' => 1.0,
                    'sum' => 0.0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_19] = $actualData[parent::TEST_CASE_NAME_18];

        $actualData[parent::TEST_CASE_NAME_20] = [
            'sum' => 14671.6,
            'origGrandTotal' => 14671.6,
            'items' => [
                '100586_1' => [
                    'price' => 1144.58,
                    'name' => 'Lf7ji4Ms',
                    'quantity' => 2,
                    'sum' => 2289.16,
                    'tax' => 'vat20',
                ],
                '100586_2' => [
                    'price' => 1144.57,
                    'name' => 'Lf7ji4Ms',
                    'quantity' => 3,
                    'sum' => 3433.71,
                    'tax' => 'vat20',
                ],
                '100587_1' => [
                    'price' => 2801.86,
                    'name' => 'RD57qiHD',
                    'quantity' => 2,
                    'sum' => 5603.72,
                    'tax' => 'vat20',
                ],
                '100587_2' => [
                    'price' => 2801.85,
                    'name' => 'RD57qiHD',
                    'quantity' => 1,
                    'sum' => 2801.85,
                    'tax' => 'vat20',
                ],
                0 => [
                    'price' => 543.16,
                    'name' => 'L7vuod9b',
                    'quantity' => 1,
                    'sum' => 543.16,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'name' => '',
                    'price' => 0,
                    'quantity' => 1,
                    'sum' => 0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_21] = [
            'sum' => 17431.01,
            'origGrandTotal' => 17431.01,
            'items' => [
                100596 => [
                    'price' => 1,
                    'quantity' => 1,
                    'sum' => 1,
                    'tax' => 'vat20',
                ],
                '100597_1' => [
                    'price' => 29.01,
                    'quantity' => 1,
                    'sum' => 29.01,
                    'tax' => 'vat20',
                ],
                '100597_2' => [
                    'price' => 29,
                    'quantity' => 29,
                    'sum' => 841,
                    'tax' => 'vat20',
                ],
                100598 => [
                    'price' => 37,
                    'quantity' => 40,
                    'sum' => 1480,
                    'tax' => 'vat20',
                ],
                100599 => [
                    'price' => 37,
                    'quantity' => 40,
                    'sum' => 1480,
                    'tax' => 'vat20',
                ],
                100600 => [
                    'price' => 37,
                    'quantity' => 40,
                    'sum' => 1480,
                    'tax' => 'vat20',
                ],
                100601 => [
                    'price' => 37,
                    'quantity' => 40,
                    'sum' => 1480,
                    'tax' => 'vat20',
                ],
                100602 => [
                    'price' => 37,
                    'quantity' => 40,
                    'sum' => 1480,
                    'tax' => 'vat20',
                ],
                100603 => [
                    'price' => 36,
                    'quantity' => 10,
                    'sum' => 360,
                    'tax' => 'vat20',
                ],
                100604 => [
                    'price' => 29,
                    'quantity' => 60,
                    'sum' => 1740,
                    'tax' => 'vat20',
                ],
                100605 => [
                    'price' => 29,
                    'quantity' => 80,
                    'sum' => 2320,
                    'tax' => 'vat20',
                ],
                100606 => [
                    'price' => 33,
                    'quantity' => 30,
                    'sum' => 990,
                    'tax' => 'vat20',
                ],
                100607 => [
                    'price' => 33,
                    'quantity' => 20,
                    'sum' => 660,
                    'tax' => 'vat20',
                ],
                100608 => [
                    'price' => 33,
                    'quantity' => 10,
                    'sum' => 330,
                    'tax' => 'vat20',
                ],
                100609 => [
                    'price' => 46,
                    'quantity' => 20,
                    'sum' => 920,
                    'tax' => 'vat20',
                ],
                100610 => [
                    'price' => 46,
                    'quantity' => 20,
                    'sum' => 920,
                    'tax' => 'vat20',
                ],
                100611 => [
                    'price' => 46,
                    'quantity' => 20,
                    'sum' => 920,
                    'tax' => 'vat20',
                ],
                100612 => [
                    'price' => 0,
                    'quantity' => 4,
                    'sum' => 0,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'price' => 0,
                    'quantity' => 1,
                    'sum' => 0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_22] = [
            'sum' => 0.0,
            'origGrandTotal' => 10.0,
            'items' => [
                100605 => [
                    'price' => 0.0,
                    'name' => 'MI1yi2wG',
                    'quantity' => 1.0,
                    'sum' => 0.0,
                    'tax' => 'vat20',
                ],
                100606 => [
                    'price' => 0.0,
                    'name' => 'oAScgwsB',
                    'quantity' => 1.0,
                    'sum' => 0.0,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'name' => '',
                    'price' => 10.0,
                    'quantity' => 1.0,
                    'sum' => 10.0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_23] = [
            'sum' => 0.0,
            'origGrandTotal' => 200.0,
            'items' => [
                100607 => [
                    'price' => 0.0,
                    'quantity' => 1.0,
                    'sum' => 0.0,
                    'tax' => 'vat20',
                ],
                100608 => [
                    'price' => 0.0,
                    'quantity' => 1.0,
                    'sum' => 0.0,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'price' => 200.0,
                    'quantity' => 1.0,
                    'sum' => 200.0,
                    'tax' => '',
                ],
            ],
        ];

        $actualData[parent::TEST_CASE_NAME_24] = [
            'sum' => 0.0,
            'origGrandTotal' => 0.0,
            'items' => [
                100609 => [
                    'price' => 0.0,
                    'name' => 'LIprnTaA',
                    'quantity' => 1.0,
                    'sum' => 0.0,
                    'tax' => 'vat20',
                ],
                'shipping' => [
                    'name' => '',
                    'price' => 0.0,
                    'quantity' => 1.0,
                    'sum' => 0.0,
                    'tax' => '',
                ],
            ],
        ];

        return $actualData;
    }

    protected function setUp()
    {
        $this->discountHelper = $this->getDiscountHelperInstance();
        $this->discountHelper->setIsSplitItemsAllowed(true);
    }
}
