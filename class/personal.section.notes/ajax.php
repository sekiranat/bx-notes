<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
    die();

use Bitrix\Main\Db\SqlQueryException;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Request;

/**
 * Class PersonalSectionNotesAjaxController
 */
class PersonalSectionNotesAjaxController extends Controller
{
    /**
     * @param Request|null $request
     *
     * @throws LoaderException
     */
    public function __construct(Request $request = null)
    {
        parent::__construct($request);

        Loader::includeModule('sale');
    }

    /**
     * @return array
     */
    public function configureActions(): array
    {
        return [
            'saveNote'      => ['prefilters' => [new ActionFilter\Authentication]],
            'removeNote'    => ['prefilters' => [new ActionFilter\Authentication]],
            'setUserRating' => ['prefilters' => [new ActionFilter\Authentication]],
        ];
    }

    /**
     * @param        $id
     * @param        $sortId
     * @param        $elementId
     * @param string $text
     *
     * @return array|null
     */
    public function saveNoteAction(int $id, string $text = '')
    {
        CBitrixComponent::includeComponentClass('tf:personal.section.notes');
        $componentClass = new PersonalSectionNotesComponent();

        $result = $componentClass->saveNote($id, $text);

        if (!$result->isSuccess())
        {
            $this->addErrors($result->getErrors());
            return null;
        }
        else
            return $result->getData();
    }

    /**
     * @param $id
     * @param $sortId
     * @param $elementId
     *
     * @return array|null
     * @throws SqlQueryException
     */
    public function removeNoteAction(int $id)
    {
        CBitrixComponent::includeComponentClass('tf:personal.section.notes');
        $componentClass = new PersonalSectionNotesComponent();

        $result = $componentClass->removeNote($id);

        if (!$result->isSuccess())
        {
            $this->addErrors($result->getErrors());
            return null;
        }
        else
            return $result->getData();
    }

    /**
     * @param int $productId
     * @param int $rating
     *
     * @return array|null
     */
    public function setUserRatingAction(int $id, int $rating = 0)
    {
        CBitrixComponent::includeComponentClass('tf:personal.section.notes');
        $componentClass = new PersonalSectionNotesComponent();

        $result = $componentClass->setUserRating($id, $rating);

        if (!$result->isSuccess())
        {
            $this->addErrors($result->getErrors());
            return null;
        }
        else
            return $result->getData();
    }
}
