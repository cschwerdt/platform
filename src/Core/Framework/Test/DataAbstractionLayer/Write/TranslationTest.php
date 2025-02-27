<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SystemSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingTranslationLanguageException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Language\LanguageDefinition;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Tax\TaxDefinition;

class TranslationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AssertArraySubsetBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->currencyRepository = $this->getContainer()->get('currency.repository');
        $this->languageRepository = $this->getContainer()->get('language.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testCurrencyWithTranslationViaLocale(): void
    {
        $name = 'US Dollar';
        $shortName = 'USD';

        $data = [
            'factor' => 1,
            'symbol' => '$',
            'decimalPrecision' => 2,
            'isoCode' => 'USD',
            'translations' => [
                'en-GB' => [
                    'name' => 'US Dollar',
                    'shortName' => 'USD',
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload = $translations->getPayloads()[0];
        $this->silentAssertArraySubset(['name' => $name], $payload);
        $this->silentAssertArraySubset(['shortName' => $shortName], $payload);
    }

    public function testCurrencyWithTranslationViaLanguageIdSimpleNotation(): void
    {
        $name = 'US Dollar';
        $shortName = 'USD';

        $data = [
            'factor' => 1,
            'decimalPrecision' => 2,
            'symbol' => '$',
            'isoCode' => 'USD',
            'translations' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'name' => 'US Dollar',
                    'shortName' => 'USD',
                    'isoCode' => 'USD',
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload = $translations->getPayloads()[0];
        $this->silentAssertArraySubset(['name' => $name], $payload);
        $this->silentAssertArraySubset(['shortName' => $shortName], $payload);
    }

    public function testCurrencyWithTranslationMergeViaLocaleAndLanguageId(): void
    {
        $name = 'US Dollar';
        $shortName = 'USD';

        $data = [
            'factor' => 1,
            'decimalPrecision' => 2,
            'symbol' => '$',
            'isoCode' => 'USD',
            'translations' => [
                'en-GB' => [
                    'name' => $name,
                ],

                Defaults::LANGUAGE_SYSTEM => [
                    'shortName' => $shortName,
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload = $translations->getPayloads()[0];
        $this->silentAssertArraySubset(['name' => $name], $payload);
        $this->silentAssertArraySubset(['shortName' => $shortName], $payload);
    }

    public function testCurrencyWithTranslationMergeOverwriteViaLocaleAndLanguageId(): void
    {
        $name = 'US Dollar';
        $shortName = 'USD';

        $data = [
            'factor' => 1,
            'decimalPrecision' => 2,
            'symbol' => '$',
            'isoCode' => 'USD',
            'translations' => [
                'en-GB' => [
                    'name' => $name,
                    'shortName' => 'should be overwritten',
                ],

                Defaults::LANGUAGE_SYSTEM => [
                    'shortName' => $shortName,
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload = $translations->getPayloads()[0];
        $this->silentAssertArraySubset(['name' => $name], $payload);
        $this->silentAssertArraySubset(['shortName' => $shortName], $payload);
    }

    public function testCurrencyWithTranslationViaLocaleAndLanguageId(): void
    {
        $germanLanguageId = Uuid::randomHex();
        $germanName = 'Amerikanischer Dollar';
        $germanShortName = 'US Dollar Deutsch';
        $englishName = 'US Dollar';
        $englishShortName = 'USD';

        $this->languageRepository->create(
            [[
                'id' => $germanLanguageId,
                'name' => 'de-DE',
                'locale' => [
                    'id' => Uuid::randomHex(),
                    'code' => 'x-tst_DE2',
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'translationCode' => [
                    'id' => Uuid::randomHex(),
                    'code' => 'x-tst_DE3',
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
            ]],
            $this->context
        );

        $data = [
            'factor' => 1,
            'decimalPrecision' => 2,
            'symbol' => '$',
            'isoCode' => 'USD',
            'translations' => [
                'en-GB' => [
                    'name' => $englishName,
                    'shortName' => $englishShortName,
                ],

                $germanLanguageId => [
                    'name' => $germanName,
                    'shortName' => $germanShortName,
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(2, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains($germanLanguageId, $languageIds);
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload1 = $translations->getPayloads()[0];
        $payload2 = $translations->getPayloads()[1];

        $this->silentAssertArraySubset(
            [
                'shortName' => $germanShortName,
                'name' => $germanName,
            ],
            $payload1
        );

        $this->silentAssertArraySubset(
            [
                'shortName' => $englishShortName,
                'name' => $englishName,
            ],
            $payload2
        );
    }

    public function testCurrencyTranslationWithCachingAndInvalidation(): void
    {
        $englishName = 'US Dollar';
        $englishShortName = 'USD';

        $data = [
            'factor' => 1,
            'symbol' => '$',
            'decimalPrecision' => 2,
            'isoCode' => 'USD',
            'translations' => [
                'en-GB' => [
                    'name' => $englishName,
                    'shortName' => $englishShortName,
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload = $translations->getPayloads()[0];
        $this->silentAssertArraySubset(['name' => $englishName], $payload);
        $this->silentAssertArraySubset(['shortName' => $englishShortName], $payload);

        $germanLanguageId = Uuid::randomHex();
        $data = [
            'id' => $germanLanguageId,
            'translationCode' => [
                'name' => 'Niederländisch',
                'code' => 'x-nl_NL',
                'territory' => 'Niederlande',
            ],
            'localeId' => $this->getLocaleIdOfSystemLanguage(),
            'name' => 'nl-NL',
        ];

        $this->languageRepository->create([$data], $this->context);

        $nlName = 'Amerikaans Dollar';
        $nlShortName = 'US Dollar German';

        $data = [
            'factor' => 1,
            'symbol' => '$',
            'decimalPrecision' => 2,
            'isoCode' => 'USD',
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'default',
                    'shortName' => 'def',
                ],
                'x-nl_NL' => [
                    'name' => $nlName,
                    'shortName' => $nlShortName,
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(2, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains($germanLanguageId, $languageIds);

        $payload = $translations->getPayloads();

        $this->silentAssertArraySubset(['name' => 'default'], $payload[0]);
        $this->silentAssertArraySubset(['shortName' => 'def'], $payload[0]);

        $this->silentAssertArraySubset(['name' => $nlName], $payload[1]);
        $this->silentAssertArraySubset(['shortName' => $nlShortName], $payload[1]);
    }

    public function testCurrencyTranslationWithInvalidLocaleCode(): void
    {
        $data = [
            'factor' => 1,
            'symbol' => '$',
            'translations' => [
                'en-UK' => [
                    'name' => 'US Dollar',
                    'shortName' => 'USD',
                ],
            ],
        ];

        $this->expectException(LanguageNotFoundException::class);
        $this->currencyRepository->create([$data], $this->context);
    }

    public function testProductWithDifferentTranslations(): void
    {
        $germanLanguageId = Uuid::randomHex();

        $result = $this->languageRepository->create(
            [[
                'id' => $germanLanguageId,
                'name' => 'de-DE',
                'locale' => [
                    'id' => Uuid::randomHex(),
                    'code' => 'x-de_DE',
                    'name' => 'locale',
                    'territory' => 'territory',
                ],
                'translationCode' => [
                    'id' => Uuid::randomHex(),
                    'code' => 'x-de_DE2',
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
            ]],
            $this->context
        );

        $languages = $result->getEventByDefinition(LanguageDefinition::class);
        static::assertCount(1, array_unique($languages->getIds()));
        static::assertContains($germanLanguageId, $languages->getIds());

        $data = [
            [
                'id' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                'productNumber' => Uuid::randomHex(),
                'manufacturer' => [
                    'id' => 'e4e8988334a34bb48d397b41a611084f',
                    'name' => 'Das blaue Haus',
                    'link' => 'http://www.blaueshaus-shop.de',
                ],
                'tax' => [
                    'id' => 'fe4eb0fd92a7417ebf8720a5148aae64',
                    'taxRate' => 19,
                    'name' => '19%',
                ],
                'price' => [
                    [
                        'currencyId' => Defaults::CURRENCY, 'gross' => 7.9899999999999824,
                        'net' => 6.7142857142857,
                        'linked' => false,
                    ],
                ],
                'translations' => [
                    $germanLanguageId => [
                        'id' => '4f1bcf3bc0fb4e62989e88b3bd37d1a2',
                        'productId' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                        'name' => 'Backform gelb',
                        'description' => 'inflo decertatio. His Manus dilabor do, eia lumen, sed Desisto qua evello sono hinc, ars his misericordite.',
                    ],
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Test En',
                    ],
                ],
                'cover' => [
                    'id' => 'd610dccf27754a7faa5c22d7368e6d8f',
                    'productId' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                    'position' => 1,
                    'media' => [
                        'id' => '4b2252d11baa49f3a62e292888f5e439',
                        'title' => 'Backform-gelb',
                    ],
                ],
                'active' => true,
                'markAsTopseller' => false,
                'stock' => 45,
                'weight' => 0,
                'minPurchase' => 1,
                'shippingFree' => false,
                'purchasePrice' => 0,
            ],
        ];

        $result = $this->productRepository->create($data, $this->context);

        $products = $result->getEventByDefinition(ProductDefinition::class);
        static::assertCount(1, $products->getIds());

        $translations = $result->getEventByDefinition(ProductManufacturerTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $translations = $result->getEventByDefinition(ProductTranslationDefinition::class);
        static::assertCount(2, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);
        static::assertContains($germanLanguageId, $languageIds);
    }

    public function testTranslationsAssociationOfMissingRoot(): void
    {
        /** @var EntityRepository $categoryRepository */
        $categoryRepository = $this->getContainer()->get('category.repository');

        $category = [
            'id' => Uuid::randomHex(),
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
            ],
        ];
        $categoryRepository->create([$category], $this->context);

        /** @var CategoryEntity $catSystem */
        $catSystem = $categoryRepository->search(new Criteria([$category['id']]), $this->context)->first();

        static::assertNotNull($catSystem);
        static::assertEquals('system', $catSystem->getName());
        static::assertEquals('system', $catSystem->getTranslated()['name']);

        $deDeContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM_DE, Defaults::LANGUAGE_SYSTEM]);
        /** @var CategoryEntity $catDeDe */
        $catDeDe = $categoryRepository->search(new Criteria([$category['id']]), $deDeContext)->first();

        static::assertNotNull($catDeDe);
        static::assertNull($catDeDe->getName());
        static::assertEquals('system', $catDeDe->getTranslated()['name']);
    }

    public function testUpsert(): void
    {
        $data = [
            [
                'id' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                'productNumber' => Uuid::randomHex(),
                'manufacturer' => [
                    'id' => 'e4e8988334a34bb48d397b41a611084f',
                    'name' => 'Das blaue Haus',
                    'link' => 'http://www.blaueshaus-shop.de',
                ],
                'tax' => [
                    'id' => 'fe4eb0fd92a7417ebf8720a5148aae64',
                    'taxRate' => 19,
                    'name' => '19%',
                ],
                'price' => [
                    [
                        'currencyId' => Defaults::CURRENCY, 'gross' => 7.9899999999999824,
                        'net' => 6.7142857142857,
                        'linked' => false,
                    ],
                ],
                'translations' => [
                    [
                        'productId' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                        'name' => 'Backform gelb',
                        'description' => 'inflo decertatio. His Manus dilabor do, eia lumen, sed Desisto qua evello sono hinc, ars his misericordite.',
                        'descriptionLong' => '
    sors capulus se Quies, mox qui Sentus dum confirmo do iam. Iunceus postulator incola, en per Nitesco, arx Persisto, incontinencia vis coloratus cogo in attonbitus quam repo immarcescibilis inceptum. Ego Vena series sudo ac Nitidus. Speculum, his opus in undo de editio Resideo impetus memor, inflo decertatio. His Manus dilabor do, eia lumen, sed Desisto qua evello sono hinc, ars his misericordite.
    ',
                        'language' => [
                            'id' => Defaults::LANGUAGE_SYSTEM,
                            'name' => 'system',
                        ],
                    ],
                ],
                'media' => [
                    [
                        'id' => 'd610dccf27754a7faa5c22d7368e6d8f',
                        'productId' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                        'isCover' => true,
                        'position' => 1,
                        'media' => [
                            'id' => '4b2252d11baa49f3a62e292888f5e439',
                            'name' => 'Backform-gelb',
                            'album' => [
                                'id' => 'a7104eb19fc649fa86cf6fe6c26ad65a',
                                'name' => 'Artikel',
                                'position' => 2,
                                'createThumbnails' => false,
                                'thumbnailSize' => '200x200;600x600;1280x1280',
                                'icon' => 'sprite-inbox',
                                'thumbnailHighDpi' => true,
                                'thumbnailQuality' => 90,
                                'thumbnailHighDpiQuality' => 60,
                            ],
                        ],
                    ],
                ],
                'active' => true,
                'markAsTopseller' => false,
                'stock' => 45,
                'weight' => 0,
                'minPurchase' => 1,
                'shippingFree' => false,
                'purchasePrice' => 0,
            ],
        ];
        $productRepo = $this->getContainer()->get('product.repository');
        $affected = $productRepo->upsert($data, Context::createDefaultContext());

        static::assertNotNull($affected->getEventByDefinition(LanguageDefinition::class));

        static::assertNotNull($affected->getEventByDefinition(ProductDefinition::class));
        static::assertNotNull($affected->getEventByDefinition(ProductTranslationDefinition::class));

        static::assertNotNull($affected->getEventByDefinition(TaxDefinition::class));

        static::assertNotNull($affected->getEventByDefinition(ProductManufacturerDefinition::class));
        static::assertNotNull($affected->getEventByDefinition(ProductManufacturerTranslationDefinition::class));

        static::assertNotNull($affected->getEventByDefinition(ProductMediaDefinition::class));
        static::assertNotNull($affected->getEventByDefinition(MediaDefinition::class));
    }

    public function testMissingTranslationLanguageViolation(): void
    {
        $categoryRepository = $this->getContainer()->get('category.repository');

        $cat = [
            'name' => 'foo',
            'translations' => [
                ['name' => 'translation without a language or languageId'],
            ],
        ];
        /* @var WriteException|null $exception */
        $exception = null;
        try {
            $categoryRepository->create([$cat], $this->context);
        } catch (WriteException $e) {
            $exception = $e;
        }

        static::assertInstanceOf(WriteException::class, $exception);
        $innerExceptions = $exception->getExceptions();
        static::assertInstanceOf(MissingTranslationLanguageException::class, $innerExceptions[0]);
    }

    public function testJsonFieldOnRootEntity(): void
    {
        $pageRepository = $this->getContainer()->get('cms_page.repository');
        $slotRepository = $this->getContainer()->get('cms_slot.repository');

        $page = [
            'type' => 'landing_page',
            'blocks' => [
                [
                    'type' => 'foo',
                    'position' => 1,
                    'slots' => [
                        [
                            'id' => Uuid::randomHex(),
                            'type' => 'foo',
                            'slot' => 'bar',
                            'config' => [],
                        ],
                        [
                            'id' => Uuid::randomHex(),
                            'type' => 'foo',
                            'slot' => 'bar',
                            'config' => [
                                'var1' => [
                                    'source' => FieldConfig::SOURCE_MAPPED,
                                    'value' => 'foo',
                                ],
                            ],
                        ],
                        [
                            'id' => Uuid::randomHex(),
                            'type' => 'foo',
                            'slot' => 'bar',
                        ],
                    ],
                ],
            ],
        ];

        $result = $pageRepository->create([$page], $this->context);

        $events = $result->getEventByDefinition(CmsSlotDefinition::class);
        $ids = $events->getIds();

        static::assertCount(3, $ids);

        $searchResult = $slotRepository->search(new Criteria($ids), $this->context);

        /** @var CmsSlotEntity $slot */
        $slot = $searchResult->getEntities()->get($page['blocks'][0]['slots'][0]['id']);
        static::assertEquals([], $slot->getConfig());

        /** @var CmsSlotEntity $slot */
        $slot = $searchResult->getEntities()->get($page['blocks'][0]['slots'][1]['id']);
        static::assertEquals(['var1' => ['source' => FieldConfig::SOURCE_MAPPED, 'value' => 'foo']], $slot->getConfig());

        /** @var CmsSlotEntity $slot */
        $slot = $searchResult->getEntities()->get($page['blocks'][0]['slots'][2]['id']);
        static::assertNull($slot->getConfig());
    }

    public function testJsonFieldWithDifferentLanguages(): void
    {
        $pageRepository = $this->getContainer()->get('cms_page.repository');
        $slotRepository = $this->getContainer()->get('cms_slot.repository');

        $page = [
            'type' => 'landing_page',
            'blocks' => [
                [
                    'type' => 'foo',
                    'position' => 1,
                    'slots' => [
                        [
                            'id' => Uuid::randomHex(),
                            'type' => 'foo',
                            'slot' => 'bar',
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['config' => []],
                                Defaults::LANGUAGE_SYSTEM_DE => ['config' => []],
                            ],
                        ],
                        [
                            'id' => Uuid::randomHex(),
                            'type' => 'foo',
                            'slot' => 'bar',
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['config' => ['var1' => ['source' => FieldConfig::SOURCE_MAPPED, 'value' => 'en']]],
                                Defaults::LANGUAGE_SYSTEM_DE => ['config' => ['var1' => ['source' => FieldConfig::SOURCE_MAPPED, 'value' => 'de']]],
                            ],
                        ],
                        [
                            'id' => Uuid::randomHex(),
                            'type' => 'foo',
                            'slot' => 'bar',
                        ],
                    ],
                ],
            ],
        ];

        $result = $pageRepository->create([$page], $this->context);

        $events = $result->getEventByDefinition(CmsSlotDefinition::class);
        $ids = $events->getIds();

        static::assertCount(3, $ids);

        // validate english translations

        $searchResult = $slotRepository->search(new Criteria($ids), $this->context);

        /** @var CmsSlotEntity $slot */
        $slot = $searchResult->getEntities()->get($page['blocks'][0]['slots'][0]['id']);
        static::assertEquals([], $slot->getConfig());

        /** @var CmsSlotEntity $slot */
        $slot = $searchResult->getEntities()->get($page['blocks'][0]['slots'][1]['id']);
        static::assertEquals(['var1' => ['source' => FieldConfig::SOURCE_MAPPED, 'value' => 'en']], $slot->getConfig());

        /** @var CmsSlotEntity $slot */
        $slot = $searchResult->getEntities()->get($page['blocks'][0]['slots'][2]['id']);
        static::assertNull($slot->getConfig());

        // validate german translations

        $germanContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM_DE, Defaults::LANGUAGE_SYSTEM]);
        $searchResult = $slotRepository->search(new Criteria($ids), $germanContext);

        /** @var CmsSlotEntity $slot */
        $slot = $searchResult->getEntities()->get($page['blocks'][0]['slots'][0]['id']);
        static::assertEquals([], $slot->getConfig());

        /** @var CmsSlotEntity $slot */
        $slot = $searchResult->getEntities()->get($page['blocks'][0]['slots'][1]['id']);
        static::assertEquals(['var1' => ['source' => FieldConfig::SOURCE_MAPPED, 'value' => 'de']], $slot->getConfig());

        /** @var CmsSlotEntity $slot */
        $slot = $searchResult->getEntities()->get($page['blocks'][0]['slots'][2]['id']);
        static::assertNull($slot->getConfig());
    }
}
