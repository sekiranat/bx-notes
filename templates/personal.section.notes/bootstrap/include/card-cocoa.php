<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @global CMain                     $APPLICATION
 * @var array                        $arParams
 * @var array                        $arResult
 * @var array                        $item
 * @var string                       $groupCode
 * @var array                        $group
 * @var CatalogSectionItemsComponent $component
 */
?>
<div class="col-12 xs:col-6 md:col-4 lmd:col-3 card card-cocoa" data-id="note-item"
     data-sort-id="<?= $item['SORT_ID'] ?>" data-item-id="<?= $item['ID'] ?>"
     <? if ($item['IBLOCK_ELEMENT_ID']) : ?>data-element-id="<?= $item['IBLOCK_ELEMENT_ID'] ?>" <? endif; ?>>
    <div class="card-container color-class <?= $item['CARD_CLASSES'] ?>">
        <a href="<?= $item['DETAIL_PAGE_URL'] ?>" class="card-top-block">
            <div class="card-marks mb-5" style="min-height: 18px;">
                <div class="card-marks-list">
                    <div class="card-mark color-made-in-torrefacto" data-mark="made-in-torrefacto"
                         <? if (!$item['MADE_IN_TORREFACTO']) : ?>hidden<? endif; ?>>
                        <svg class="icon icon-small fire card-icon color-made-in-torrefacto">
                            <use xlink:href="#fire"></use>
                        </svg>
                        <span class="text-extra">Сделано в Torrefacto</span>
                    </div>
                </div>
            </div>

            <div class="text-large text-extra" data-block-id="product-name"
                 data-prop-name="<?= trim($item['NAME'] . ' ' . $item['SUB_NAME']) ?>">
                <?= $item['NAME'] ?>
            </div>
            <div class="text-small" data-block-id="bought-times"></div>

            <div class="card-rating mt-10 mb-15">
                <div class="flex justify-start items-center">
                    <div class="stars mr-5 d-flex items-center">
                        <? for ($i = 1; $i <= 5; $i++) : ?>
                            <svg class="icon icon-small <?= round($item['RATING']) >= $i ? 'active' : '' ?>">
                                <use xlink:href="#star"></use>
                            </svg>
                        <? endfor; ?>
                    </div>
                    <div class="score">
                        <span class="inline-block"><?= $item['RATING']; ?></span>
                        <? if ($item['RATING_COUNT'] > 0) : ?>
                            <span class="inline-block pl-5">(<?= $item['RATING_COUNT'] ?> <?= $item['RATING_COUNT_TEXT'] ?>)</span>
                        <? endif; ?>
                    </div>
                    <? if ($arResult['USER_DATA']['IS_AUTHORIZED'] && false) : ?>
                        <div class="favorite ml-5">
                            <svg class="icon icon-small icon-heart <?= $item['IS_FAVORITE'] ? '-active' : '' ?>">
                                <use xlink:href="#heart"></use>
                            </svg>
                        </div>
                    <? endif; ?>
                </div>
            </div>

            <div class="card-cover card-cover-transparent card-cover-large-height<?= !empty($item['BOX_CONTENT_PRODUCTS']) || $item['PRODUCT_TYPE'] == 'kit' ? ' card-cover-wide' : '' ?>">
                <img data-src="<?= $item['DETAIL_PICTURE']['RESIZED'] ? $item['DETAIL_PICTURE']['RESIZED']['src'] : $item['DETAIL_PICTURE']['SRC'] ?>"
                     src="" alt="<?= $item['NAME'] ?>" title="<?= $item['NAME'] ?>">
            </div>

            <div class="card-desc text-body mt-10 mb-10 sm:mb-0">
                <?= $item['PREVIEW_TEXT'] ?>
            </div>

            <div class="card-scales text-small mt-10"
                 <? if (isset($item['SORT_MAIN_PROP'])) : ?>data-block-href<? endif; ?>>
                <? if (isset($item['SORT_MAIN_PROPS'])) : ?>
                    <div class="row">
                        <? foreach ($item['SORT_MAIN_PROPS'] as $prop) : ?>
                            <? if ($prop['CODE'] == 'SCA_SCORE') : ?>
                                <? continue; ?>
                            <? endif; ?>
                            <div class="card-scale col-6 mb-10">
                                <div class="card-scale-name"><?= $prop['NAME']; ?></div>
                                <div class="card-scale-values pr-5 xxs:pr-15 sm:pr-5">
                                    <? for ($i = 1; $i < 10; $i++) : ?>
                                        <div class="card-scale-value <?= $prop['VALUE'] >= $i ? 'active' : '' ?>"></div>
                                    <? endfor; ?>
                                </div>
                            </div>
                        <? endforeach; ?>
                        <? if ($item['SORT_MAIN_PROPS']['SCA_SCORE']) : ?>
                            <div class="col-6 mb-10" data-behavior="tooltip" data-template-id="scale-sca-tooltip"
                                 data-max-width="300px" data-theme="brown" data-placement="top-start"
                                 data-distance="10">
                                <div class="">Рейтинг SCA:</div>
                                <div class="text-bold"><?= $item['SORT_MAIN_PROPS']['SCA_SCORE']['TEXT']; ?></div>
                            </div>
                        <? endif; ?>
                    </div>
                <? endif; ?>
            </div>
        </a>

        <div class="markup">
            <div class="card-rip -extended">
                <div class="card-rip-notch"></div>
                <div class="card-rip-notch"></div>
            </div>

            <div class="card-user-rating">
                <label class="text-small block no-event mb-10">Ваша оценка <span class="text-xsmall text-muted pl-5">(видна только вам)</span></label>
                <div class="card-rating mt-10 mb-15" data-behavior="modal" data-id="product-rating">
                    <div class="stars flex items-center justify-start">
                        <? for ($i = 1; $i <= 5; $i++) : ?>
                            <svg class="icon icon-small <?= round($item['USER_RATING']) >= $i ? 'active' : '' ?>">
                                <use xlink:href="#star"></use>
                            </svg>
                        <? endfor; ?>
                    </div>
                </div>
            </div>

            <div class="">
                <label class="text-small block no-event mb-10">Ваша заметка</label>
                <? if (!empty($item['NOTE_TEXT'])) : ?>
                    <div class="text-body mv-110 mb-15 overflow-hidden <?= $item['NOTE_CLASS'] ?>"
                         data-id="note-text"><?= $item['NOTE_TEXT'] ?></div>
                    <div class="field -fade text-body mb-15" data-id="note-edit-block" hidden>
                        <textarea name="sort-note-<?= $item['ID'] ?>" id="sort-note-<?= $item['ID'] ?>" cols="30"
                                  rows="5" placeholder="Ваша заметка"><?= $item['NOTE_TEXT'] ?></textarea>
                    </div>
                    <a class="button button-common block" data-id="edit-note">Редактировать</a>
                <? else : ?>
                    <div class="text-body mv-110 mb-15 overflow-hidden" data-id="note-text" hidden></div>
                    <div class="field -fade text-body mb-15" data-id="note-edit-block">
                        <textarea name="sort-note-<?= $item['ID'] ?>" id="sort-note-<?= $item['ID'] ?>" cols="30"
                                  rows="5" placeholder="Ваша заметка"></textarea>
                    </div>
                    <a class="button button-common block button-disabled" data-id="save-note">Сохранить</a>
                <? endif; ?>
                <a class="button button-second block mt-10" data-id="remove-note">Удалить сорт</a>
            </div>
        </div>

        <? if ($item['SORT_MAIN_PROPS']['SCA_SCORE']) : ?>
            <div id="scale-tooltip-<?= $item['ID'] ?>" hidden>
                <div class="text-small sca-tooltip">
                    <div class="text-caps text-bold mb-10">Рейтинг SCA</div>
                    <div class="">
                        SCA — некоммерческая ассоциация, разрабатывающая связанные с кофе регламенты,
                        принятые во всем мире. Рейтинг SCA — органолептическая оценка — измеряется в
                        баллах до 100 и основывается на таких параметрах, как аромат молотого и
                        заваренного кофе, вкус, послевкусие, кислинка, насыщенность, сбалансированность
                        и некоторые другие.
                        <br><br>
                        Рейтинг SCA в Торрефакто проставляют Q-грейдеры — специалисты по оценке качества
                        кофе. Сегодня в мире всего менее 10 тысяч Q-грейдеров.
                        <br>
                        <br><a href="https://russia.sca.coffee/" rel="nofollow noopener" target="_blank"
                               class="link link-color">https://russia.sca.coffee/</a>
                    </div>
                </div>
            </div>
        <? endif; ?>
    </div>
</div>
