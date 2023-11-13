<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Application;
use Bitrix\Main\Page\Asset;

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */

\Bitrix\Main\UI\Extension::load("ui.notification");


$this->addExternalJs('https://cdn.jsdelivr.net/npm/overlayscrollbars@1.12.0/js/OverlayScrollbars.min.js');
$this->addExternalCss('https://cdn.jsdelivr.net/npm/overlayscrollbars@1.12.0/css/OverlayScrollbars.min.css');

Asset::getInstance()->addCss($this->getFolder() . '/css/style.min.css', true);
Asset::getInstance()->addJs($this->GetFolder() . '/js/script.min.js', true);
$documentRoot = Application::getDocumentRoot();
?>

<div class="mb-25 mt-50 hidden md:block">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1 class="text-large text-extra">Личный кабинет</h1>
            </div>
        </div>
    </div>
</div>

<div class="wrapper pt-60 mb-60" id="personal-section-notes">
    <div class="container container-semi-large">
        <div class="md:hidden text-large text-extra mb-20">Избранное</div>
        <? if (!empty($arResult['NOTES_GROUPS'])) : ?>
            <? foreach ($arResult['NOTES_GROUPS'] as $groupCode => $group) : ?>
                <? if (!empty($group['ITEMS'])) : ?>
                    <div class="row mt-30 mb-30">
                        <div class="col-12">
                            <div class="text-large text-extra">
                                <?= $group['NAME'] ?>
                            </div>
                        </div>
                    </div>
                    <div class="row cards" data-entity="items-row">
                        <? foreach ($group['ITEMS'] as $item) : ?>
                            <?
                            if ($groupCode === 'roasted')
                                $fileName = 'card-coffee.php';
                            if ($groupCode === 'cocoa')
                                $fileName = 'card-cocoa.php';
                            if ($groupCode === 'nuts-dried-fruits')
                                $fileName = 'card-nut-fruit.php';
                            if ($groupCode === 'spices-salt-sugar')
                                $fileName = 'card-spice-salt-sugar.php';
                            if ($groupCode === 'tea')
                                $fileName = 'card-tea.php';
                            ?>
                            <? $file = new \Bitrix\Main\IO\File($documentRoot . $templateFolder . '/include/' . $fileName); ?>
                            <? $isFileExists = $file->isExists(); ?>
                            <? if ($isFileExists) : ?>
                                <? include($file->getPath()); ?>
                            <? endif; ?>
                        <? endforeach; ?>
                    </div>
                <? endif; ?>
            <? endforeach; ?>
        <? else : ?>
            <div class="container">
                Добавьте в избранное сорта из <a href="/catalog/" class="link link-color">каталога</a>
            </div>
        <? endif; ?>
    </div>
</div>

<div id="product-rating" hidden>
    <div class="modal-wrap sl--scrollable">
        <div class="modal overflow-initial">
            <div class="px-20 py-20">
                <div class="flex justify-end">
                    <button class="block" data-close>
                        <svg class="icon block">
                            <use xlink:href="#pow"></use>
                        </svg>
                    </button>
                </div>
                <div class="text-center mb-15">
                    <div class="modal-heading tracking-wide text-bold">Ваша оценка</div>
                </div>

                <form name="product-rating" id="user-product-rating">
                    <div class="card-rating rating mt-10 mb-15">
                        <div class="row">
                            <div class="col-8 xs:col-6 m-auto stars flex justify-between">
                                <? for ($i = 1; $i <= 5; $i++) : ?>
                                    <svg class="icon icon-body">
                                        <use xlink:href="#star"></use>
                                    </svg>
                                <? endfor; ?>
                            </div>
                        </div>
                    </div>

                    <div class="submit-block">
                        <button class="button button-common button-block" type="submit" name="submit-form" value="Подтвердить">Подтвердить
                        </button>
                    </div>
                </form>
                <div class="modal-footer" hidden>
                    <hr class="my-15">
                    <div class="notify-block"></div>
                </div>
            </div>
        </div>
    </div>
</div>