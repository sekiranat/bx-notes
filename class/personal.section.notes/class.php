<?

use Bitrix\Blog\CommentTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\Result;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserTable;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Internals\BasketPropertyTable;
use Bitrix\Sale\Order;
use Local\Product\Favorite\FavoriteTable;
use Local\Product\Favorite\Helper as FavoriteHelper;
use Bitrix\Main\FileTable;
use Local\Product\Rating\Helper as RatingHelper;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

CBitrixComponent::includeComponentClass('tf:personal.section');

/**
 * Class PersonalSectionNotesComponent
 */
class PersonalSectionNotesComponent extends PersonalSectionComponent
{
    const PROPERTY_OPTIONS   = ['ID', 'CODE', 'NAME', 'VALUE_ENUM', 'VALUE_XML_ID', 'VALUE'];
    const NOTES_IBLOCK_ID    = 21;
    const PRODUCTS_IBLOCK_ID = 14;
    const RATING_IBLOCK_ID   = 15;
    const FLAG_ICON_MAP      = [
        'bestseller'        => ['TYPE' => 'svg', 'MAP' => 'icon-sort-bestseller', 'ACTIVE' => true, 'CLASS' => 'color-bestseller'],
        'novyy_urozhay'     => ['TYPE' => 'svg', 'MAP' => 'icon-sort-new-harvest', 'ACTIVE' => true, 'CLASS' => 'color-new-harvest'],
        'nash_vybor'        => ['TYPE' => 'svg', 'MAP' => 'icon-sort-our-choice', 'ACTIVE' => true, 'CLASS' => 'color-our-choice'],
        'mikrolot'          => ['TYPE' => 'svg', 'MAP' => 'icon-sort-microlot', 'ACTIVE' => true, 'CLASS' => 'color-microlot'],
        'sort_nedeli'       => ['TYPE' => 'svg', 'MAP' => 'icon-sort-sort-of-week', 'ACTIVE' => true, 'CLASS' => 'color-sort-of-week'],
        'sort_mesyatsa'     => ['TYPE' => 'svg', 'MAP' => 'icon-sort-sort-of-week', 'ACTIVE' => false, 'CLASS' => 'color-sort-of-month'], // только для оптовиков
        '50percent'         => ['TYPE' => 'svg', 'MAP' => '50-percent', 'ACTIVE' => true, 'CLASS' => 'icon-special-width'],
        'discount_included' => ['TYPE' => 'svg', 'MAP' => 'icon-sort-discount-included', 'ACTIVE' => true, 'CLASS' => 'color-discount-included'],
    ];
    const CATEGORIES         = [
        'roasted'           => [
            'NAME'       => 'Кофе',
            'CODE'       => 'roasted',
            'SORT_ORDER' => 1,
        ],
        'cocoa'             => [
            'NAME'       => 'Шоколад',
            'CODE'       => 'cocoa',
            'SORT_ORDER' => 2,
        ],
        'tea'               => [
            'NAME'       => 'Чай',
            'CODE'       => 'tea',
            'SORT_ORDER' => 3,
        ],
        'nuts-dried-fruits' => [
            'NAME'       => 'Орехи и сухофрукты',
            'CODE'       => 'nuts-dried-fruits',
            'SORT_ORDER' => 4,
        ],
        'spices-salt-sugar' => [
            'NAME'       => 'Специи, соль и сахар',
            'CODE'       => 'spices-salt-sugar',
            'SORT_ORDER' => 5,
        ],
    ];

    private static $productNotes = [];
    private static $notes = [];
    private static $groupedNotes = [];

    /**
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function executeComponent()
    {
        // 0. устанавливаем user_id для дальнейших операций
        global $USER;
        static::$uid = $USER->GetID();

        if (static::$uid)
        {
            // 1. получить данные о пользователе (ФИО, телефон, емейл, город и т.д.)
            $this->getUserData();
            // 2. получить данные по вн.счету (если есть)
            $this->getUserAccountData();
            // 3. получить данные по количеству комментариев и т.д.
            $this->getUserRestData();
            // 4. получить список товаров в избранном
            $this->getNoteSorts();
            // 5. получить URL, NAME, DESCRIPTION, SECTION_CODE
            $this->getProducts();
            // группировка по категориям
            $this->groupNotesByCategory();
            // получить свойства товаров
            $this->getPropsNotes();

            // 6. сортировка товаров по группе
            $this->sortNotes();

            // 7. сортировка по категории
            $this->sortGroups();

            // 8. сформировать результирующий массив
            $this->fillResultData();
        }

        $this->includeComponentTemplate();
    }

    /**
     * @param int $uid
     *
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getUserData(int $uid = 0): array
    {
        $filter = [
            'filter' => ['ID' => $uid ?: static::$uid],
            'select' => ['ID', 'EMAIL', 'UF_PERSONAL_DISCOUNT'],
        ];
        $res    = UserTable::getList($filter);
        if ($user = $res->fetch())
        {
            // format user response
            $user['DISCOUNT']            = $user['UF_PERSONAL_DISCOUNT'] ?: 0;
            $this->arResult['USER_DATA'] = $user;

            return $user;
        }

        return [];
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function getUserRestData()
    {
        // количество комментариев и отзывов
        $this->arResult['USER_COMMENTS_COUNT'] = 0;
        // отзывы
        $filter                                = [
            'order'  => ['ID' => 'DESC'],
            'filter' => ['IBLOCK_ID' => static::REVIEWS_IBLOCK_ID, 'CREATED_USER_ID' => static::$uid],
            'select' => ['ID'],
        ];
        $rsIblock                              = CIBlockElement::GetList($filter['order'], $filter['filter'], false, false, $filter['select']);
        $this->arResult['USER_COMMENTS_COUNT'] += $rsIblock->SelectedRowsCount();

        // комментарии
        $filter                                = [
            'order'  => ['ID' => 'DESC'],
            'filter' => ['IBLOCK_ID' => static::COMMENTS_IBLOCK_ID, 'CREATED_USER_ID' => static::$uid],
            'select' => ['ID'],
        ];
        $rsIblock                              = CIBlockElement::GetList($filter['order'], $filter['filter'], false, false, $filter['select']);
        $this->arResult['USER_COMMENTS_COUNT'] += $rsIblock->SelectedRowsCount();

        // комментарии к блогу
        $filter                                = [
            'order'  => ['ID' => 'DESC'],
            'filter' => ['AUTHOR_ID' => static::$uid],
            'select' => ['ID'],
        ];
        $rsBlogPostComments                    = CommentTable::getList($filter);
        $this->arResult['USER_COMMENTS_COUNT'] += $rsBlogPostComments->getSelectedRowsCount();
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getNoteSorts()
    {
        // TODO - переделать эту анархию! привести к виду "либо инфоблок, либо таблица" (+старый дизайн!)
        // получаем список сортов из таблицы
        $favorites = FavoriteHelper::getUserFavorites(static::$uid);

        foreach ($favorites as $favorite)
        {
            static::$notes[$favorite['ID']] = [
                'ID'          => (int) $favorite['ID'],
                'USER_ID'     => (int) $favorite['USER_ID'],
                'SORT_ID'     => (int) $favorite['PRODUCT_ID'],
                'USER_RATING' => (int) $favorite['RATING'],
                'NOTE_TEXT'   => $favorite['COMMENT'],
            ];
        }
    }

    /**
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getProducts()
    {
        if (empty(static::$notes))
            return;

        $products   = [];
        $productIds = array_column(static::$notes, 'ID', 'SORT_ID');

        $filter = [
            'order'  => ['ID' => 'DESC'],
            'filter' => ['ID' => array_keys($productIds)],
            'select' => ['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID', 'IBLOCK_ID', 'ACTIVE', 'PREVIEW_TEXT', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'],
        ];

        $rsIblockElement = CIBlockElement::GetList($filter['order'], $filter['filter'], false, false, $filter['select']);
        while ($iblock = $rsIblockElement->GetNext())
        {

            $product = &$products[$iblock['ID']];

            $product = [
                'ID'                => $iblock['ID'],
                'DETAIL_PAGE_URL'   => $iblock['DETAIL_PAGE_URL'],
                'NAME'              => $iblock['NAME'],
                'ACTIVE'            => $iblock['ACTIVE'],
                'IBLOCK_SECTION_ID' => $iblock['IBLOCK_SECTION_ID'],
                'PREVIEW_TEXT'      => $iblock['PREVIEW_TEXT'],
                'PREVIEW_PICTURE'   => $iblock['PREVIEW_PICTURE'],
                'DETAIL_PICTURE'    => $iblock['DETAIL_PICTURE'],
                'PROPERTIES'        => [],
            ];

            if (!empty($product['PREVIEW_PICTURE']))
                $imageFilter['filter']['ID'][] = $product['PREVIEW_PICTURE'];

            if (!empty($product['DETAIL_PICTURE']))
                $imageFilter['filter']['ID'][] = $product['DETAIL_PICTURE'];

            // получить изображения
            $images = [];
            if (!empty($imageFilter['filter']['ID']))
            {
                $rsFile = FileTable::getList($imageFilter);
                while ($file = $rsFile->fetch())
                {
                    $images[$file['ID']] = [
                        'ID'        => $file['ID'],
                        'WIDTH'     => $file['WIDTH'],
                        'HEIGHT'    => $file['HEIGHT'],
                        'SUBDIR'    => $file['SUBDIR'],
                        'FILE_NAME' => $file['FILE_NAME'],
                        'SRC'       => '/upload/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'],
                    ];
                }
            }

            if ($product['PREVIEW_PICTURE'] && $images[$product['PREVIEW_PICTURE']])
                $product['PREVIEW_PICTURE'] = $images[$product['PREVIEW_PICTURE']];

            if ($product['DETAIL_PICTURE'] && $images[$product['DETAIL_PICTURE']])
                $product['DETAIL_PICTURE'] = $images[$product['DETAIL_PICTURE']];
        }

        // заполняем информацию из заметок
        foreach (static::$notes as $note)
        {
            $pid = $note['SORT_ID'];

            if (isset($products[$pid]))
            {
                $products[$pid]['ID']                = $note['ID'];
                $products[$pid]['SORT_ID']           = $note['SORT_ID'];
                $products[$pid]['NOTE_TEXT']         = $note['NOTE_TEXT'];
                $products[$pid]['USER_RATING']       = $note['USER_RATING'];
                $products[$pid]['IBLOCK_ELEMENT_ID'] = $note['IBLOCK_ELEMENT_ID']; // элемент с текстом в инфоблоке, нужен для редактирования заметки
            }
        }
        unset($note);

        // Заполняем категории для каждого товара
        $sections = [];
        $rsGroups = CIBlockElement::GetElementGroups(array_keys($products), true, ['ID', 'CODE', 'NAME', 'SORT', 'IBLOCK_SECTION_ID', 'SECTION_PAGE_URL', 'IBLOCK_ELEMENT_ID']);

        while ($group = $rsGroups->Fetch())
            $sections[$group['IBLOCK_ELEMENT_ID']][$group['CODE']] = $group;

        foreach ($products as $key => &$product)
        {
            $product['SECTIONS'] = $sections[$key];
        }
        unset($product);

        static::$productNotes = $products;
    }

    /** Группировка товаров по категориям.
     *
     * @return void
     */
    private function groupNotesByCategory()
    {
        foreach (static::$productNotes as $id => $product)
        {
            $productDistributed = false;
            foreach (static::CATEGORIES as $categoryData)
            {
                if (in_array($categoryData['CODE'], array_keys($product['SECTIONS'])) && !$productDistributed)
                {
                    static::$groupedNotes[$categoryData['CODE']]['NAME']       = $categoryData['NAME'];
                    static::$groupedNotes[$categoryData['CODE']]['SORT_ORDER'] = $categoryData['SORT_ORDER'];
                    static::$groupedNotes[$categoryData['CODE']]['ITEMS'][$id] = $product;
                }
            }
        }
    }

    /**
     * Получение свойств товаров.
     *
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getPropsNotes()
    {
        $this->getCoffeeProps();
        $this->getFruitsAndNutsProps();
        $this->getTeaProps();
        $this->getCocoaProps();
        $this->getSpicesSaltSugarProps();

        // получить данные по "Куплен Х раз"
        $this->getBoughtSortsData();
    }

    /**
     * Получение свойств кофе
     *
     * @return void
     */
    private function getCoffeeProps()
    {
        if (!empty(static::$groupedNotes['roasted']))
        {
            $notes = &static::$groupedNotes['roasted']['ITEMS'];

            $options = ['PROPERTY_FIELDS' => static::PROPERTY_OPTIONS];
            if (!empty($notes))
                CIBlockElement::GetPropertyValuesArray(
                    $notes,
                    static::PRODUCTS_IBLOCK_ID,
                    ['ID' => array_keys($notes)],
                    ['CODE' => ['MTEXT', 'GRPB', 'NUMBER', 'RATING', 'ZNK', 'KISLINKA', 'K2', 'CHAR_CHOCO_ACIDITY', 'G', 'N', 'SCA_SCORE', 'AVAILABLE_WEIGHT', 'NAL', 'IS_SORT_OF_WEEK', 'IS_PROMO_PRODUCT']],
                    $options
                );

            foreach ($notes as &$note)
            {
                $note['GROUP'] = $note['PROPERTIES']['GRPB']['VALUE_XML_ID'];

                if ($note['PROPERTIES']['REGION'] && !empty($note['PROPERTIES']['REGION']['VALUE']))
                {
                    $name             = $note['NAME'];
                    $note['NAME']     = $note['PROPERTIES']['REGION']['VALUE'];
                    $note['SUB_NAME'] = trim(str_replace($note['PROPERTIES']['REGION']['VALUE'], "", $name));
                }

                $note['NUMBER']         = $note['PROPERTIES']['NUMBER']['VALUE'];
                $note['RATING']         = $note['PROPERTIES']['RATING']['VALUE'];
                $note['SORT_MAIN_PROP'] = $note['PROPERTIES']['KISLINKA']['VALUE_SORT'];

                // классы для контейнера
                if ($note['IS_SORT_OF_WEEK'])
                    $note['CARD_CLASSES'][] = 'sort-of-week';

                if ($note['IS_PROMO_PRODUCT'])
                    $note['CARD_CLASSES'][] = 'promo-product';

                if ($note['IS_GREEN'] == 'Y')
                    $note['CARD_CLASSES'][] = 'color-class-grn';
                else
                    $note['CARD_CLASSES'][] = 'color-class-' . mb_strtolower($note['GROUP']);

                if (!empty($note['CARD_CLASSES']))
                    $note['CARD_CLASSES'] = implode(' ', $note['CARD_CLASSES']);
                else
                    $note['CARD_CLASSES'] = '';

                // Доступность товара
                if (!empty($note['PROPERTIES']['NAL']['VALUE']) || (!empty($note['PROPERTIES']['AVAILABLE_WEIGHT']) && $note['PROPERTIES']['AVAILABLE_WEIGHT'] < 0) || $note['ACTIVE'] != 'Y')
                    $note['AVAILABLE'] = false;
                else
                    $note['AVAILABLE'] = true;

                // Иконки у карточки сорта
                $note['ICONS'] = [];

                $itemIconsProp = $note['PROPERTIES']['ZNK']['VALUE_XML_ID'];
                if ($itemIconsProp)
                {
                    $iconsList = [];
                    foreach ((array) $itemIconsProp as $k => $itemIcon)
                    {
                        if (array_key_exists($itemIcon, static::FLAG_ICON_MAP) && static::FLAG_ICON_MAP[$itemIcon]['ACTIVE'])
                        {
                            $icon = static::FLAG_ICON_MAP[$itemIcon];
                            $icon = array_merge($icon, ['NAME' => $note['PROPERTIES']['ZNK']['VALUE'][$k]]);

                            if ($itemIcon == '50percent')
                                $icon['NAME'] = 'скидка';

                            $iconsList[$icon['MAP']] = $icon;
                        }
                    }

                    if (!empty($iconsList))
                    {
                        $secondPosition = [];
                        if (isset($iconsList['nash_vybor']) && count($iconsList) >= 2)
                        {
                            $secondPosition['nash_vybor'] = $iconsList['nash_vybor'];
                            unset($iconsList['nash_vybor']);
                        }

                        $counter = 0;
                        foreach ($iconsList as $data)
                        {
                            if ($counter > 0 && !empty($secondPosition))
                                $note['ICONS'][] = $secondPosition['nash_vybor'];

                            $note['ICONS'][] = $data;

                            $counter++;
                        }
                    }
                }

                // Название группы сорта
                if ($note['PROPERTIES']['GRPB']['VALUE'])
                    $note['GROUP'] = $note['PROPERTIES']['GRPB']['VALUE_XML_ID'];

                // Основное свойство сорта (значение шкалы шоколад-кислинка)
                if ($note['PROPERTIES']['CHAR_CHOCO_ACIDITY']['VALUE'])
                {
                    if (floatval($note['PROPERTIES']['CHAR_CHOCO_ACIDITY']['VALUE']) > 100)
                        $note['PROPERTIES']['CHAR_CHOCO_ACIDITY']['VALUE'] = 100;

                    $note['SORT_MAIN_PROP'] = floatval($note['PROPERTIES']['CHAR_CHOCO_ACIDITY']['VALUE']);
                }

                // Свойства-шкалы
                foreach (['K2', 'G', 'N', 'SCA_SCORE'] as $name)
                {
                    $value = $note['PROPERTIES'][$name]['VALUE'];

                    if ($name == 'SCA_SCORE' && !$value)
                        continue;

                    $note['SORT_MAIN_PROPS'][$name] = [
                        'NAME'  => $note['PROPERTIES'][$name]['NAME'],
                        'CODE'  => $name,
                        'VALUE' => in_array($name, ['N', 'K2', 'G']) ? intval($value) : $value,
                    ];

                    if ($name == 'K2')
                    {
                        if ($value <= 1)
                            $note['SORT_MAIN_PROPS'][$name]['TEXT'] = 'Низкая';
                        elseif ($value >= 2 && $value <= 3)
                            $note['SORT_MAIN_PROPS'][$name]['TEXT'] = 'Средняя';
                        elseif ($value >= 4)
                        {
                            $note['SORT_MAIN_PROPS'][$name]['TEXT']            = 'Высокая';
                            $note['SORT_MAIN_PROPS'][$name]['CLASS']           = 'high-level';
                            $note['SORT_MAIN_PROPS'][$name]['IS_HIGH_LEVEL']   = true;
                            $note['SORT_MAIN_PROPS'][$name]['HIGH_LEVEL_TEXT'] = 'Сорт с ощутимой кислинкой';
                        }
                    }

                    if ($name == 'SCA_SCORE')
                    {
                        $value = str_replace(',', '.', $value);
                        $value = floatval($value);

                        $note['SORT_MAIN_PROPS'][$name]['TEXT'] = number_format($value, 2, '.', '');

                        if ($value - floor($value) == 0)
                            $note['SORT_MAIN_PROPS'][$name]['TEXT'] = number_format($value, 0, '.', '');
                    }
                }

                // проверка капсульного кофе
                if ($note['IBLOCK_SECTION_ID'] == 42)
                    $note['IS_CAPSULE'] = true;

                // проверка дрип-пакетов
                if ($note['IBLOCK_SECTION_ID'] == 55)
                    $note['IS_DRIP_COFFEE'] = true;

                $note['NOTE_TEXT']  = htmlspecialchars_decode($note['NOTE_TEXT']);
                $note['NOTE_CLASS'] = '';

                if (mb_strlen($note['NOTE_TEXT']) > 145)
                    $note['NOTE_CLASS'] = 'note-size-1';

                if (mb_strlen($note['NOTE_TEXT']) > 190)
                    $note['NOTE_CLASS'] = 'overflow-custom';
            }

            // рейтинг - количество голосов за товар
            $countsVotes = RatingHelper::getCountVotesByProducts(array_keys($notes));

            foreach ($countsVotes as $productId => $count)
            {
                $notes[$productId]['RATING_COUNT']      = $count;
                $notes[$productId]['RATING_COUNT_TEXT'] = $this->getTextInclineByValue($count, ['голос', 'голоса', 'голосов']);
            }
        }
    }

    /**
     * Получение свойств чая
     *
     * @return void
     */
    private function getTeaProps()
    {
        if (!empty(static::$groupedNotes['tea']))
        {
            $notes = &static::$groupedNotes['tea']['ITEMS'];

            $options = ['PROPERTY_FIELDS' => static::PROPERTY_OPTIONS];
            if (!empty($notes))
                CIBlockElement::GetPropertyValuesArray(
                    $notes,
                    static::PRODUCTS_IBLOCK_ID,
                    ['ID' => array_keys($notes)],
                    ['CODE' => ['NAME_RUSSIAN', 'NAME_CHINESE', 'NUMBER', 'RATING', 'ZNK', 'AVAILABLE_WEIGHT', 'NAL', 'TEA_TYPE', 'IS_SORT_OF_WEEK', 'IS_PROMO_PRODUCT']],
                    $options
                );

            foreach ($notes as &$note)
            {
                $note['NUMBER']         = $note['PROPERTIES']['NUMBER']['VALUE'];
                $note['NAME_CHINESE']   = $note['PROPERTIES']['NAME_CHINESE']['VALUE'];
                $note['SORT_MAIN_PROP'] = $note['PROPERTIES']['KISLINKA']['VALUE_SORT'];
                $note['NAME_RUSSIAN']   = $note['PROPERTIES']['NAME_RUSSIAN']['VALUE'];
                $note['RATING']         = $note['PROPERTIES']['RATING']['VALUE'];
                $note['NOTE_TEXT']      = htmlspecialchars_decode($note['NOTE_TEXT']);


                // классы для контейнера
                $group                  = $note['PROPERTIES']['TEA_TYPE']['VALUE_XML_ID'];
                $note['CARD_CLASSES'][] = 'color-class-' . mb_strtolower($group);

                if ($note['IS_SORT_OF_WEEK'])
                    $note['CARD_CLASSES'][] = 'sort-of-week';

                if ($note['IS_PROMO_PRODUCT'])
                    $note['CARD_CLASSES'][] = 'promo-product';

                if (!empty($note['CARD_CLASSES']))
                    $note['CARD_CLASSES'] = implode(' ', $note['CARD_CLASSES']);
                else
                    $note['CARD_CLASSES'] = '';

                // Доступность товара
                if (!empty($note['PROPERTIES']['NAL']['VALUE']) || (!empty($note['PROPERTIES']['AVAILABLE_WEIGHT']) && $note['PROPERTIES']['AVAILABLE_WEIGHT'] < 0) || $note['ACTIVE'] != 'Y')
                    $note['AVAILABLE'] = false;
                else
                    $note['AVAILABLE'] = true;

                if (mb_strlen($note['NOTE_TEXT']) > 145)
                    $note['NOTE_CLASS'] = 'note-size-1';

                if (mb_strlen($note['NOTE_TEXT']) > 190)
                    $note['NOTE_CLASS'] = 'overflow-custom';
            }
        }

        if (isset($notes) && $notes)
        {
            // рейтинг - количество голосов за товар
            $countsVotes = RatingHelper::getCountVotesByProducts(array_keys($notes));

            foreach ($countsVotes as $productId => $count)
            {
                $notes[$productId]['RATING_COUNT']      = $count;
                $notes[$productId]['RATING_COUNT_TEXT'] = $this->getTextInclineByValue($count, ['голос', 'голоса', 'голосов']);
            }
        }
    }

    /**
     * Получение свойств шоколада
     *
     * @return void
     */
    private function getCocoaProps()
    {
        if (!empty(static::$groupedNotes['cocoa']))
        {
            $notes = &static::$groupedNotes['cocoa']['ITEMS'];

            $options = ['PROPERTY_FIELDS' => static::PROPERTY_OPTIONS];
            if (!empty($notes))
                CIBlockElement::GetPropertyValuesArray(
                    $notes,
                    static::PRODUCTS_IBLOCK_ID,
                    ['ID' => array_keys($notes)],
                    ['CODE' => ['RATING', 'ZNK', 'K2', 'CHAR_CHOCO_ACIDITY', 'G', 'MADE_IN_TORREFACTO', 'ASTRINGENCY', 'PREVIEW_TEXT', 'COCOA_GROUP', 'AVAILABLE_WEIGHT', 'NAL', 'IS_PROMO_PRODUCT']],
                    $options
                );

            foreach ($notes as &$note)
            {
                $note['GROUP'] = $note['PROPERTIES']['GRPB']['VALUE_XML_ID'];

                if ($note['PROPERTIES']['REGION'] && !empty($note['PROPERTIES']['REGION']['VALUE']))
                {
                    $name             = $note['NAME'];
                    $note['REGION']   = $note['PROPERTIES']['REGION']['VALUE'];
                    $note['SUB_NAME'] = trim(str_replace($note['PROPERTIES']['REGION']['VALUE'], "", $name));
                }
                $note['NUMBER']             = $note['PROPERTIES']['NUMBER']['VALUE'];
                $note['RATING']             = $note['PROPERTIES']['RATING']['VALUE'];
                $note['SORT_MAIN_PROP']     = $note['PROPERTIES']['KISLINKA']['VALUE_SORT'];
                $note['MADE_IN_TORREFACTO'] = $note['PROPERTIES']['MADE_IN_TORREFACTO']['VALUE_SORT'] === 'Y';


                // классы для контейнера
                $note['CARD_CLASSES'][] = 'card-cocoa';

                if ($note['IS_PROMO_PRODUCT'])
                    $note['CARD_CLASSES'][] = 'promo-product';

                if (!empty($note['CARD_CLASSES']))
                    $note['CARD_CLASSES'] = implode(' ', $note['CARD_CLASSES']);
                else
                    $note['CARD_CLASSES'] = '';

                // Доступность товара
                if (!empty($note['PROPERTIES']['NAL']['VALUE']) || (!empty($note['PROPERTIES']['AVAILABLE_WEIGHT']) && $note['PROPERTIES']['AVAILABLE_WEIGHT'] < 0) || $note['ACTIVE'] != 'Y')
                    $note['AVAILABLE'] = false;
                else
                    $note['AVAILABLE'] = true;

                // Основное свойство сорта (значение шкалы шоколад-кислинка)
                if ($note['PROPERTIES']['CHAR_CHOCO_ACIDITY']['VALUE'])
                {
                    if (floatval($note['PROPERTIES']['CHAR_CHOCO_ACIDITY']['VALUE']) > 100)
                        $note['PROPERTIES']['CHAR_CHOCO_ACIDITY']['VALUE'] = 100;

                    $note['SORT_MAIN_PROP'] = floatval($note['PROPERTIES']['CHAR_CHOCO_ACIDITY']['VALUE']);
                }

                $itemIconsProp = $note['PROPERTIES']['ZNK']['VALUE_XML_ID'];

                // Флаг сорта недели
                if (in_array('sort_nedeli', $itemIconsProp))
                    $note['IS_SORT_OF_WEEK'] = true;

                // Флаг сорта сделано в torrefacto
                if (in_array('made_in_torrefacto', $itemIconsProp))
                    $note['MADE_IN_TORREFACTO'] = true;

                // Выводим только для группы "Горький, темный и молочный шоколад. Какао-бобы"
                if ($note['PROPERTIES']['COCOA_GROUP']['VALUE_XML_ID'] === 'cocoa')
                {
                    // Свойства-шкалы
                    foreach (['K2', 'G', 'SCA_SCORE', 'ASTRINGENCY'] as $name)
                    {
                        $value = $note['PROPERTIES'][$name]['VALUE'];

                        if ($name == 'SCA_SCORE' && !$value)
                            continue;

                        $note['SORT_MAIN_PROPS'][$name] = [
                            'NAME'  => $note['PROPERTIES'][$name]['NAME'],
                            'CODE'  => $name,
                            'VALUE' => in_array($name, ['K2', 'G']) ? intval($value) : $value,
                        ];
                    }
                }

                $note['NOTE_TEXT'] = htmlspecialchars_decode($note['NOTE_TEXT']);

                if (mb_strlen($note['NOTE_TEXT']) > 145)
                    $note['NOTE_CLASS'] = 'note-size-1';

                if (mb_strlen($note['NOTE_TEXT']) > 190)
                    $note['NOTE_CLASS'] = 'overflow-custom';
            }

            // рейтинг - количество голосов за товар
            $countsVotes = RatingHelper::getCountVotesByProducts(array_keys($notes));

            foreach ($countsVotes as $productId => $count)
            {
                $notes[$productId]['RATING_COUNT']      = $count;
                $notes[$productId]['RATING_COUNT_TEXT'] = $this->getTextInclineByValue($count, ['голос', 'голоса', 'голосов']);
            }
        }
    }

    /**
     * Получение свойств сухофруктов и орехов
     *
     * @return void
     */
    private function getFruitsAndNutsProps()
    {
        if (!empty(static::$groupedNotes['nuts-dried-fruits']))
        {
            $notes = &static::$groupedNotes['nuts-dried-fruits']['ITEMS'];

            $options = ['PROPERTY_FIELDS' => static::PROPERTY_OPTIONS];
            if (!empty($notes))
                CIBlockElement::GetPropertyValuesArray(
                    $notes,
                    static::PRODUCTS_IBLOCK_ID,
                    ['ID' => array_keys($notes)],
                    ['CODE' => ['REGION', 'NUMBER', 'RATING', 'ZNK', 'SHORT_NAME', 'COUNTRY_OF_ORIGIN', 'AVAILABLE_WEIGHT', 'NAL', 'IS_SORT_OF_WEEK', 'IS_PROMO_PRODUCT']],
                    $options
                );

            foreach ($notes as &$note)
            {
                $note['RATING'] = $note['PROPERTIES']['RATING']['VALUE'];
                $note['NUMBER'] = $note['PROPERTIES']['NUMBER']['VALUE'];

                // классы для контейнера
                $product['CARD_CLASSES'][] = 'card-nuts-dried-fruits';

                if ($product['IS_SORT_OF_WEEK'])
                    $product['CARD_CLASSES'][] = 'sort-of-week';

                if ($product['IS_PROMO_PRODUCT'])
                    $product['CARD_CLASSES'][] = 'promo-product';

                if (!empty($note['CARD_CLASSES']))
                    $note['CARD_CLASSES'] = implode(' ', $note['CARD_CLASSES']);
                else
                    $note['CARD_CLASSES'] = '';

                // Доступность товара
                if (!empty($note['PROPERTIES']['NAL']['VALUE']) || (!empty($note['PROPERTIES']['AVAILABLE_WEIGHT']) && $note['PROPERTIES']['AVAILABLE_WEIGHT'] < 0) || $note['ACTIVE'] != 'Y')
                    $note['AVAILABLE'] = false;
                else
                    $note['AVAILABLE'] = true;

                // Флаг сорта недели
                $itemIconsProp = $note['PROPERTIES']['ZNK']['VALUE_XML_ID'];
                if (in_array('sort_nedeli', $itemIconsProp))
                    $note['IS_SORT_OF_WEEK'] = true;

                $note['NOTE_TEXT'] = htmlspecialchars_decode($note['NOTE_TEXT']);

                if (mb_strlen($note['NOTE_TEXT']) > 145)
                    $note['NOTE_CLASS'] = 'note-size-1';

                if (mb_strlen($note['NOTE_TEXT']) > 190)
                    $note['NOTE_CLASS'] = 'overflow-custom';

                $subName = [];

                if (!empty($note['PROPERTIES']['SHORT_NAME']['VALUE']))
                    $subName[] = $note['PROPERTIES']['SHORT_NAME']['VALUE'];

                if (!empty($note['PROPERTIES']['COUNTRY_OF_ORIGIN']['VALUE']))
                    $subName[] = $note['PROPERTIES']['COUNTRY_OF_ORIGIN']['VALUE'];

                if (!empty($subName))
                    $note['SUB_NAME'] = implode(' • ', $subName);
            }

            // рейтинг - количество голосов за товар
            $countsVotes = RatingHelper::getCountVotesByProducts(array_keys($notes));

            foreach ($countsVotes as $productId => $count)
            {
                $notes[$productId]['RATING_COUNT']      = $count;
                $notes[$productId]['RATING_COUNT_TEXT'] = $this->getTextInclineByValue($count, ['голос', 'голоса', 'голосов']);
            }
        }
    }

    /**
     * Получение свойств специй, соли, сахара
     *
     * @return void
     */
    private function getSpicesSaltSugarProps()
    {
        if (!empty(static::$groupedNotes['spices-salt-sugar']))
        {
            $notes = &static::$groupedNotes['spices-salt-sugar']['ITEMS'];

            $options = ['PROPERTY_FIELDS' => static::PROPERTY_OPTIONS];

            if (!empty($notes))
            {
                CIBlockElement::GetPropertyValuesArray(
                    $notes,
                    static::PRODUCTS_IBLOCK_ID,
                    ['ID' => array_keys($notes)],
                    ['CODE' => ['REGION', 'NUMBER', 'RATING', 'ZNK', 'SHORT_NAME', 'COUNTRY_OF_ORIGIN', 'AVAILABLE_WEIGHT', 'NAL', 'IS_SORT_OF_WEEK', 'IS_PROMO_PRODUCT']],
                    $options
                );
            }

            foreach ($notes as &$note)
            {
                $note['RATING'] = $note['PROPERTIES']['RATING']['VALUE'];
                $note['NUMBER'] = $note['PROPERTIES']['NUMBER']['VALUE'];

                // классы для контейнера
                $product['CARD_CLASSES'][] = 'card-spices-salt-sugar';

                if ($product['IS_SORT_OF_WEEK'])
                    $product['CARD_CLASSES'][] = 'sort-of-week';

                if ($product['IS_PROMO_PRODUCT'])
                    $product['CARD_CLASSES'][] = 'promo-product';

                if (!empty($note['CARD_CLASSES']))
                    $note['CARD_CLASSES'] = implode(' ', $note['CARD_CLASSES']);
                else
                    $note['CARD_CLASSES'] = '';

                // Доступность товара
                if (!empty($note['PROPERTIES']['NAL']['VALUE']) || (!empty($note['PROPERTIES']['AVAILABLE_WEIGHT']) && $note['PROPERTIES']['AVAILABLE_WEIGHT'] < 0) || $note['ACTIVE'] != 'Y')
                    $note['AVAILABLE'] = false;
                else
                    $note['AVAILABLE'] = true;

                // Флаг сорта недели
                $itemIconsProp = $note['PROPERTIES']['ZNK']['VALUE_XML_ID'];
                if (in_array('sort_nedeli', $itemIconsProp))
                    $note['IS_SORT_OF_WEEK'] = true;

                $note['NOTE_TEXT'] = htmlspecialchars_decode($note['NOTE_TEXT']);

                if (mb_strlen($note['NOTE_TEXT']) > 145)
                    $note['NOTE_CLASS'] = 'note-size-1';

                if (mb_strlen($note['NOTE_TEXT']) > 190)
                    $note['NOTE_CLASS'] = 'overflow-custom';

                $subName = [];

                if (!empty($note['PROPERTIES']['SHORT_NAME']['VALUE']))
                    $subName[] = $note['PROPERTIES']['SHORT_NAME']['VALUE'];

                if (!empty($note['PROPERTIES']['COUNTRY_OF_ORIGIN']['VALUE']))
                    $subName[] = $note['PROPERTIES']['COUNTRY_OF_ORIGIN']['VALUE'];

                if (!empty($subName))
                    $note['SUB_NAME'] = implode(' • ', $subName);
            }

            // рейтинг - количество голосов за товар
            $countsVotes = RatingHelper::getCountVotesByProducts(array_keys($notes));

            foreach ($countsVotes as $productId => $count)
            {
                $notes[$productId]['RATING_COUNT']      = $count;
                $notes[$productId]['RATING_COUNT_TEXT'] = $this->getTextInclineByValue($count, ['голос', 'голоса', 'голосов']);
            }
        }
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getBoughtSortsData()
    {
        global $USER;

        $cache = Cache::createInstance();

        $uid       = static::$uid ?: $USER->GetID();
        $cacheTime = 604000;
        $cacheId   = "familiars{$uid}";
        $cachePath = "/familiars/user{$uid}/";

        $quantity       = [];
        $quantityOffers = [];

        if ($uid)
        {
            if ($cache->initCache($cacheTime, $cacheId, $cachePath))
            {
                $vars     = $cache->getVars();
                $quantity = $vars['FAMILIARS'];
            }
            else
            {
                // получаем все товары
                $products = [];
                $filter   = [
                    'order'  => ['ID' => 'ASC'],
                    'filter' => ['ACTIVE' => 'Y', 'IBLOCK_ID' => 14],
                    'select' => ['ID', 'IBLOCK_ID', 'NAME'],
                ];
                $rsIblock = CIBlockElement::GetList($filter['order'], $filter['filter'], false, false, $filter['select']);
                while ($iblock = $rsIblock->Fetch())
                {
                    $products[$iblock['ID']] = ['ID' => $iblock['ID'], 'OFFERS' => []];
                }

                // получаем все торг.предложения
                $filter   = [
                    'order'  => ['ID' => 'ASC'],
                    'filter' => ['PROPERTY_CML2_LINK' => $products, 'IBLOCK_ID' => 16, 'ACTIVE' => 'Y'],
                    'select' => ['ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_CML2_LINK'],
                ];
                $rsIblock = CIBlockElement::GetList($filter['order'], $filter['filter'], false, false, $filter['select']);
                while ($iblock = $rsIblock->Fetch())
                {
                    if ($iblock['PROPERTY_CML2_LINK_VALUE'] && $products[$iblock['PROPERTY_CML2_LINK_VALUE']])
                    {
                        $products[$iblock['PROPERTY_CML2_LINK_VALUE']]['OFFERS'][] = $iblock['ID'];
                    }
                }

                // получаем все торг.предложения и простые товары в один массив
                $offers = [];
                foreach ($products as $pid => $product)
                {
                    if (empty($product['OFFERS']))
                        $offers[] = $pid;
                    else
                        $offers = array_merge($offers, $product['OFFERS']);
                }
                $offers = array_unique($offers);

                // получаем все заказы пользователя
                $filter   = [
                    'order'  => ['ID' => 'ASC'],
                    'filter' => ['USER_ID' => $uid, 'STATUS_ID' => 'F', '>ID' => 3000000],
                    'select' => ['ID'],
                ];
                $orders   = Order::getList($filter)->fetchAll();
                $orders   = array_column($orders, 'ID');
                $packsIds = [WF_NEW_PACK_150_4, WF_NEW_PACK_MARCH_150_4, WF_LARGE_PACK_450_4, WF_LARGE_PACK_150_8, WF_LARGE_PACK_150_12, WF_NEW_PACK_MARCH_ID, WF_NEW_PACK_ID, TF_2017_PACK_1, TF_2017_PACK_2, TF_2017_PACK_3, TF_2017_PACK_4];

                // получаем все товары в заказах пользователя
                if (!empty($orders))
                {
                    $filter      = [
                        'order'  => ['NAME' => 'ASC', 'ID' => 'ASC'],
                        'filter' => ['ORDER_ID' => $orders, 'LID' => SITE_ID, 'PRODUCT_ID' => $offers],
                        'select' => ['ID', 'PRODUCT_ID', 'ORDER_ID', 'QUANTITY'],
                    ];
                    $basketItems = Basket::getList($filter)->fetchAll();

                    foreach ($basketItems as $item)
                    {
                        // если товар - набор, надо проверить содержимое
                        if (in_array($item['PRODUCT_ID'], $packsIds))
                        {
                            $filter = [
                                'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
                                'filter' => ['BASKET_ID' => $item['ID'], 'CODE' => 'WF_PACK_TORGS'],
                                'select' => ['ID', 'CODE', 'VALUE'],
                            ];
                            if ($basketItemProp = BasketPropertyTable::getList($filter)->fetch())
                            {
                                $packPositions = explode('|', $basketItemProp['VALUE']);
                                foreach ($packPositions as &$position)
                                {
                                    $position = intval($position);
                                }
                                unset($position);

                                foreach ($packPositions as $positionId)
                                {
                                    $quantityOffers[$positionId][] = $item['ORDER_ID'];
                                }
                            }
                        }
                        else
                        {
                            $quantityOffers[$item['PRODUCT_ID']][] = $item['ORDER_ID'];
                        }
                    }

                    foreach ($quantityOffers as $offerId => $ordersArr)
                    {
                        $quantityOffers[$offerId] = array_unique($ordersArr);
                    }

                    foreach ($products as $pid => $product)
                    {
                        $quantity[$pid] = 0;

                        if (!empty($product['OFFERS']))
                        {
                            foreach ($product['OFFERS'] as $offerId)
                            {
                                if ($quantityOffers[$offerId])
                                    $quantity[$pid] += count($quantityOffers[$offerId]);
                            }
                        }
                        elseif ($quantityOffers[$pid])
                        {
                            $quantity[$pid] += count($quantityOffers[$pid]);
                        }
                    }

                    if ($cacheTime > 0)
                    {
                        $cache->startDataCache($cacheTime, $cacheId, $cachePath);
                        $cache->endDataCache(['FAMILIARS' => $quantity]);
                    }
                }
            }
        }

        foreach (static::$groupedNotes as &$group)
        {
            foreach ($group['ITEMS'] as $pid => &$product)
            {
                if ($quantity[$pid] > 0)
                {
                    $product['BOUGHT_TIMES']      = $quantity[$pid];
                    $product['BOUGHT_TIMES_TEXT'] = implode(' ', ['Куплен', $quantity[$pid], $this->getTextInclineByValue($quantity[$pid], ['раз', 'раза', 'раз'])]);
                }
            }
        }
    }

    /**
     * @return void
     */
    private function sortNotes(): void
    {
        if (empty(static::$groupedNotes))
            return;

        foreach (static::$groupedNotes as $group)
        {
            uasort($group['ITEMS'], function ($a, $b) {
                if (intval($a['PROPERTIES']['NUMBER']['VALUE']) == intval($b['PROPERTIES']['NUMBER']['VALUE']))
                    return 0;

                if ($a['PROPERTIES']['GRPB']['VALUE_SORT'] == $b['PROPERTIES']['GRPB']['VALUE_SORT'])
                {
                    if (intval($a['PROPERTIES']['NUMBER']['VALUE']) == intval($b['PROPERTIES']['NUMBER']['VALUE']))
                        return 0;

                    return (intval($a['PROPERTIES']['NUMBER']['VALUE']) < intval($b['PROPERTIES']['NUMBER']['VALUE'])) ? -1 : 1;
                }

                return ($a['PROPERTIES']['GRPB']['VALUE_SORT'] < $b['PROPERTIES']['GRPB']['VALUE_SORT']) ? -1 : 1;
            });
        }
    }

    /**
     * @return void
     */
    private function sortGroups(): void
    {
        if (empty(static::$groupedNotes))
            return;

        uasort(static::$groupedNotes, function ($a, $b) {
            return $a['SORT_ORDER'] > $b['SORT_ORDER'] ? 1 : -1;
        });
    }

    /**
     * @return void
     */
    private function fillResultData(): void
    {
        if (empty(static::$groupedNotes))
            return;

        $this->arResult['NOTES_GROUPS'] = static::$groupedNotes;
    }

    /**
     * @param int    $id
     * @param string $text
     *
     * @return Result
     */
    public function saveNote(int $id, string $text = ''): Result
    {
        $result = new Result();

        global $USER;
        static::$uid = $USER->GetID();

        if (!static::$uid)
            return $result->addError(new \Bitrix\Main\Error('Ошибка, пожалуйста, свяжитесь с нами', 101));

        try
        {
            FavoriteTable::update(
                $id,
                ['COMMENT' => $text]
            );
        }
        catch (Exception $error)
        {
            $result->addError(new \Bitrix\Main\Error('Ошибка, пожалуйста, свяжитесь с нами', 102));
        }

        return $result;
    }

    /**
     * @param int $id
     *
     * @return Result
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public function removeNote(int $id): Result
    {
        $result = new Result();

        global $USER;
        static::$uid = $USER->GetID();

        if (!static::$uid || !$id)
            return $result->addError(new \Bitrix\Main\Error('Ошибка, пожалуйста, свяжитесь с нами', 101));

        try
        {
            FavoriteTable::delete(
                $id,
            );
        }
        catch (Exception $error)
        {
            $result->addError(new \Bitrix\Main\Error('Ошибка, пожалуйста, свяжитесь с нами', 102));
        }

        return $result;
    }

    /**
     * @param int $id
     * @param int $rating
     *
     * @return Result
     */
    public function setUserRating(int $id, int $rating = 0): Result
    {
        $result = new Result();

        global $USER;
        static::$uid = $USER->GetID();

        if (!static::$uid || !$id)
            return $result->addError(new \Bitrix\Main\Error('Ошибка, пожалуйста, свяжитесь с нами', 101));

        try
        {
            FavoriteTable::update(
                $id,
                ['RATING' => $rating]
            );
        }
        catch (Exception $error)
        {
            $result->addError(new \Bitrix\Main\Error('Ошибка, пожалуйста, свяжитесь с нами', 102));
        }

        return $result;
    }
}