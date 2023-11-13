export default class PersonalSectionNotesComponent {
    constructor(params) {
        if (!params) {
            params = {};
        }
        this.pageBlock = document.getElementById('personal-section-notes');
        this.modal = false;
        this.isMobile = BX.browser.IsMobile();
        this.productId = false;
        this.itemFavoriteId = false;
        this.userRating = false;
        this.helperInstance = window.commonHelperComponent || null;

        BX.UI.Notification.Center.setStackDefaults('bottom-right', {offsetX: 100});
        BX.UI.Notification.Center.setStackDefaults('top-center', {offsetY: 200});
    }

    init() {
        this.calculateCardInnerHeights();
        this.setScrollBar();
        this.setListeners();
    }

    setListeners() {
        // клик на "редактировать": ajax + нотис
        // клик на "сохранить": ajax + нотис
        // клик на "удалить сорт": ajax + нотис
        document.addEventListener('click', event => {
            this.clickEvent(event);
        });

        // набор текста в поле заметки
        document.addEventListener('input', event => {
            this.inputEvent(event);
        });

        // событие изменения размеров окна
        window.addEventListener('resize', () => {
            this.calculateCardInnerHeights();
        });

        // наведение и клик на рейтинг
        if (this.pageBlock && this.pageBlock.querySelector('.card')) {
            this.pageBlock.querySelectorAll('.card').forEach(card => {
                card.querySelector('.card-user-rating').querySelectorAll('.icon').forEach(icon => {
                    icon.addEventListener('mouseenter', event => this.mouseEnterRating(event));
                    icon.addEventListener('mouseleave', event => this.mouseLeaveRating(event));
                    icon.addEventListener('click', event => this.ratingVote(event));
                });
            });
        }

        // TODO - глобально в скрипте надо оптимизировать количество скриптов и событий, убрать BX.bind и привести к единому формату
        // наведение и клик на рейтинг в модалке
        document.addEventListener('mouseover', event => this.mouseOverOnModal(event));
        document.addEventListener('mouseout', event => this.mouseOutOnModal(event));
        document.addEventListener('submit', event => this.submitRatingVoteModal(event));

        // показ модалки с подтверждением рейтинга товара
        document.addEventListener('modal:shown', event => {
            window.__debug && console.log(event, event.data);
            if (event.data.modal) {
                this.modal = event.data.modal;

                if (this.modal.querySelector('.rating')) {
                    this.modal.querySelector('.rating').classList.add('allow');

                    if (this.userRating > 0) {
                        this.modal.querySelector('.rating').querySelectorAll('svg').forEach((item, index) => {
                            if (this.userRating >= index + 1) {
                                item.classList.add('hovered', 'active');
                            }
                        });
                    }

                    this.modal.querySelector('.rating').addEventListener('click', this.clickOnModal)
                }
            }
        });

        // сброс текущего указателя на модальное окно
        document.addEventListener('modal:hidden', event => {
            window.__debug && console.log(event, event.data);
            if (event.data.modal) {
                if (this.modal.querySelector('.rating')) {
                    this.modal.querySelector('.rating').removeEventListener('click', this.clickOnModal)

                }

                this.modal = undefined;
            }
        });
    }

    setScrollBar() {
        OverlayScrollbars(this.pageBlock.querySelectorAll('.overflow-custom'), {});
    }

    clickEvent(event) {
        var target = event.target;
        var id = target && target.getAttribute('data-id') ? target.getAttribute('data-id') : null;

        // клик на кнопку с атрибутом data-href
        if (target.hasAttribute('data-href') && target.getAttribute('data-href')) {
            location.href = target.getAttribute('data-href');
        }

        if (!id) {
            return false;
        }

        switch (id) {
            case 'edit-note':
                this.editSortNote(target);
                break;
            case 'save-note':
                this.saveSortNote(target);
                break;
            case 'remove-note':
                this.removeSortNote(target);
                break;
        }
    }

    inputEvent(event) {
        var target = event.target;
        var block = target ? target.closest('[data-id=note-edit-block]') : null;

        if (!block) {
            return false;
        }

        if (block.nextElementSibling) {
            if (target.value && target.value.length !== 0) {
                block.nextElementSibling.classList.remove('button-disabled');
            } else if (target.value === target.defaultValue) {
                block.nextElementSibling.classList.add('button-disabled');
            }
        }
    }

    calculateCardInnerHeights() {
        const rows = document.querySelectorAll('[data-entity="items-row"]');

        rows.forEach(row => {
            const cards = row.querySelectorAll('.card');

            let rowWidth = row.getBoundingClientRect();
            rowWidth = rowWidth.width;

            let cardWidth = cards[0].getBoundingClientRect();
            cardWidth = cardWidth.width;

            const cols = Math.floor(rowWidth / cardWidth);

            // на мобильных нечего выравнивать
            if (cols === 1 || isNaN(cols))
                return;

            let cardsPerRow = [];
            let cardPerRow = [];

            cards.forEach((card, index) => {
                const idx = index + 1;

                cardPerRow.push(card);

                if (idx % cols === 0 || idx === cards.length) {
                    cardsPerRow.push(cardPerRow);
                    cardPerRow = [];
                }
            });

            cardsPerRow.forEach(cardsInRow => {
                let heightsInRow = cardsInRow.map(card => {
                    let heights = {};
                    let el;

                    el = card.querySelector('.card-marks-list');
                    heights.marks = el && el.textContent.length > 0 ? el.getBoundingClientRect().height : 0;

                    el = card.querySelector('[data-block-id="product-name"]');
                    heights.name = el && el.textContent.length > 0 ? el.getBoundingClientRect().height : 0;

                    el = card.querySelector('.sub-name');
                    heights.subName = el && el.textContent.length > 0 ? el.getBoundingClientRect().height : 0;

                    el = card.querySelector('[data-block-id="bought-times"]');
                    heights.boughtTimes = el && el.textContent.length > 0 ? el.getBoundingClientRect().height : 0;

                    el = card.querySelector('.card-desc');
                    heights.desc = el && el.textContent.length > 0 ? el.getBoundingClientRect().height : 0;

                    el = card.querySelector('.card-scales .row');
                    heights.scale = el && el.querySelectorAll('.col-6').length > 2 ? el.getBoundingClientRect().height : 0;

                    return heights;
                });

                let heights = heightsInRow.reduce((prev, current) => {
                    let result = {};

                    ['marks', 'name', 'subName', 'boughtTimes', 'desc', 'scale'].forEach(param => {
                        result[param] = prev[param] || 0;

                        if (current.hasOwnProperty(param) && result[param] <= current[param]) {
                            result[param] = current[param];
                        }
                    });

                    return result;
                });

                cardsInRow.forEach(card => {
                    let el;

                    el = card.querySelector('.card-marks-list');
                    if (el) {
                        el.style.minHeight = (heights.marks ? heights.marks : 0) + 'px';
                        if (card.classList.contains('card-cocoa')) {
                            el.style.minHeight = '18px';
                        }
                    }

                    el = card.querySelector('[data-block-id="product-name"]');
                    if (el) {
                        el.style.minHeight = (heights.name ? heights.name : 0) + 'px';
                    }

                    el = card.querySelector('.sub-name');
                    if (el) {
                        el.style.minHeight = (heights.subName ? heights.subName : 0) + 'px';
                    }

                    el = card.querySelector('[data-block-id="bought-times"]');
                    if (el) {
                        el.style.minHeight = (heights.boughtTimes ? heights.boughtTimes : 0) + 'px';
                    }

                    el = card.querySelector('.card-desc');
                    if (el) {
                        el.style.minHeight = (heights.desc ? heights.desc : 0) + 'px';
                    }

                    el = card.querySelector('.card-scales .row');
                    if (el) {
                        el.style.minHeight = (heights.scale ? heights.scale : 0) + 'px';
                    }
                });
            });
        });
    }

    editSortNote(element) {
        if (!element) {
            return false;
        }

        var block = element.closest('[data-id=note-item]');

        if (block) {
            if (block.querySelector('[data-id=note-text]')) {
                block.querySelector('[data-id=note-text]').hidden = true;
            }
            if (block.querySelector('[data-id=note-edit-block]')) {
                block.querySelector('[data-id=note-edit-block]').hidden = false;
            }
            element.setAttribute('data-id', 'save-note');
            element.innerText = 'Сохранить';
        }
    }

    saveSortNote(element) {
        if (!element) {
            return false;
        }

        const block = element.closest('[data-id=note-item]');
        const itemFavoriteId = block.hasAttribute('data-item-id') ? block.getAttribute('data-item-id') : false;
        let text = '', defaultText;

        if (block && block.querySelector('[data-id=note-edit-block]') && block.querySelector('[data-id=note-edit-block]').querySelector('textarea')) {
            text = block.querySelector('[data-id=note-edit-block]').querySelector('textarea').value;
            defaultText = block.querySelector('[data-id=note-edit-block]').querySelector('textarea').defaultValue;
        }

        if (block && (text.length !== 0 || text !== defaultText) && itemFavoriteId) {
            // ajax
            this.helperInstance && this.helperInstance.initPreloader();
            BX.ajax({
                url: this.buildUrlQuery('saveNote'),
                method: 'POST',
                dataType: 'json',
                data: {
                    id: itemFavoriteId,
                    text: text
                },
                onsuccess: res => {
                    if (typeof res === "string") {
                        res = JSON.parse(res);
                    }

                    window.__debug && console.log(res);
                    this.helperInstance && this.helperInstance.removePreloader();

                    if (res.hasOwnProperty('status')) {
                        if (res.status === 'error') {
                            this.helperInstance.handleCommonErrors(res.errors);
                        } else {
                            // all ok
                            // установить data-element-id
                            if (res.hasOwnProperty('data') && res.data.hasOwnProperty('RECORD_ID') && !block.dataset.elementId) {
                                block.dataset.elementId = res.data['RECORD_ID'];
                            }

                            if (text.length !== 0) {
                                if (block.querySelector('[data-id=note-edit-block]')) {
                                    block.querySelector('[data-id=note-edit-block]').hidden = true;
                                }
                                if (block.querySelector('[data-id=note-text]')) {
                                    block.querySelector('[data-id=note-text]').hidden = false;

                                    if (block.querySelector('[data-id=note-text]').querySelector('.os-content')) {
                                        block.querySelector('[data-id=note-text]').querySelector('.os-content').innerHTML = text;
                                    } else {
                                        block.querySelector('[data-id=note-text]').innerHTML = text;
                                    }

                                    // проверка текста на длину и включение scrollbar
                                    if (text.length > 145) {
                                        block.querySelector('[data-id=note-text]').classList.add('note-size-1')
                                    }
                                    if (text.length > 190) {
                                        block.querySelector('[data-id=note-text]').classList.add('overflow-custom');
                                        OverlayScrollbars(block.querySelector('[data-id=note-text]'), {});
                                    }
                                }
                                element.setAttribute('data-id', 'edit-note');
                                element.innerText = 'Редактировать';
                            } else {
                                element.classList.add('button-disabled');
                            }

                            // показать успешный notify
                            this.helperInstance.handleNotify('Заметка обновлена');
                        }
                    } else {
                        this.helperInstance.handleCommonErrors(['Произошла непредвиденная ошибка. Пожалуйста, попробуйте позже или свяжитесь с нами']);
                    }
                },
                onfailure: error => {
                    this.helperInstance && this.helperInstance.removePreloader();
                    this.helperInstance.handleCommonErrors(['Произошла непредвиденная ошибка. Пожалуйста, попробуйте позже или свяжитесь с нами']);
                },
            });
        } else {
            // visual only
            if (block.querySelector('[data-id=note-edit-block]')) {
                block.querySelector('[data-id=note-edit-block]').hidden = true;
            }
            if (block.querySelector('[data-id=note-text]')) {
                block.querySelector('[data-id=note-text]').hidden = false;

                if (block.querySelector('[data-id=note-text]').querySelector('.os-content')) {
                    block.querySelector('[data-id=note-text]').querySelector('.os-content').innerHTML = text;
                } else {
                    block.querySelector('[data-id=note-text]').innerHTML = text;
                }
            }
            element.setAttribute('data-id', 'edit-note');
            element.innerText = 'Редактировать';
        }
    }

    removeSortNote(element) {
        if (!element) {
            return false;
        }

        var block = element.closest('[data-id=note-item]');

        if (block) {
            const itemFavoriteId = block.hasAttribute('data-item-id') ? block.getAttribute('data-item-id') : false;

            if (itemFavoriteId) {
                // ajax
                this.helperInstance && this.helperInstance.initPreloader();
                BX.ajax({
                    url: this.buildUrlQuery('removeNote'),
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        id: itemFavoriteId,
                    },
                    onsuccess: res => {
                        if (typeof res === "string") {
                            res = JSON.parse(res);
                        }

                        window.__debug && console.log(res);
                        this.helperInstance && this.helperInstance.removePreloader();

                        if (res.hasOwnProperty('status')) {
                            if (res.status === 'error') {
                                this.helperInstance.handleCommonErrors(res.errors);
                            } else {
                                // all ok
                                block.style.opacity = 0;
                                setTimeout(() => {
                                    block.remove();
                                    this.calculateCardInnerHeights();
                                }, 400);

                                // показать успешный notify
                                this.helperInstance.handleNotify('Сорт удален из избранного');
                            }
                        } else {
                            this.helperInstance.handleCommonErrors(['Произошла непредвиденная ошибка. Пожалуйста, попробуйте позже или свяжитесь с нами']);
                        }
                    },
                    onfailure: error => {
                        this.helperInstance && this.helperInstance.removePreloader();
                        this.helperInstance.handleCommonErrors(['Произошла непредвиденная ошибка. Пожалуйста, попробуйте позже или свяжитесь с нами']);
                    },
                });
            } else {
                // visual only
                if (block.querySelector('[data-id=note-edit-block]')) {
                    block.querySelector('[data-id=note-edit-block]').hidden = true;
                }
                if (block.querySelector('[data-id=note-text]')) {
                    block.querySelector('[data-id=note-text]').hidden = false;

                    if (block.querySelector('[data-id=note-text]').querySelector('.os-content')) {
                        block.querySelector('[data-id=note-text]').querySelector('.os-content').innerHTML = text;
                    } else {
                        block.querySelector('[data-id=note-text]').innerHTML = text;
                    }
                }
                element.setAttribute('data-id', 'edit-note');
                element.innerText = 'Редактировать';
            }
        }
    }

    mouseEnterRating(event) {
        var target = event.target;
        var parent = BX.findParent(target, {class: 'card-rating'});
        var previousElements = [];

        if (parent) {
            previousElements.push(target);  // включая текущий элемент
            var element = target.previousElementSibling;
            while (element) {
                previousElements.push(element);
                element = element.previousElementSibling;
            }

            if (previousElements.length !== 0) {
                previousElements.forEach(item => item.classList.add('hovered'));
            }
        }
    }

    mouseLeaveRating(event) {
        var target = event.target;
        var parent = BX.findParent(target, {class: 'card-rating'});
        var previousElements = [];

        if (parent) {
            previousElements.push(target);  // включая текущий элемент
            var element = target.previousElementSibling;
            while (element) {
                previousElements.push(element);
                element = element.previousElementSibling;
            }

            if (previousElements.length !== 0) {
                previousElements.forEach(item => item.classList.remove('hovered'));
            }
        }
    }

    mouseLeaveRatingModal(event) {
        var target = event.target;
        var parent = BX.findParent(target, {class: 'rating'});
        var nextElements = [];

        if (parent && parent.classList.contains('allow')) {
            if (event.dropAll) {
                parent.querySelectorAll('svg').forEach(item => nextElements.push(item));
            } else {
                var element = target.nextElementSibling;
                while (element) {
                    nextElements.push(element);
                    element = element.nextElementSibling;
                }
            }

            if (nextElements.length !== 0) {
                nextElements.forEach(item => item.classList.remove('hovered'));
            }
        }
    }

    ratingVote(event) {
        var target = event.target;
        var parent = BX.findParent(target, {class: 'card-rating'});

        if (parent) {
            // TODO - голосование и попап с результатами (оценка установлена)
            this.productId = parent.closest('.card') ? parent.closest('.card').getAttribute('data-sort-id') : false;
            this.itemFavoriteId = parent.closest('.card') ? parent.closest('.card').getAttribute('data-item-id') : false;
            this.userRating = parent.querySelector('.stars').querySelectorAll('svg.hovered').length;
        } else {
            // блокируем вывод модального окна
            event.preventDefault();
            event.stopPropagation();
        }
    }

    submitRatingVoteModal(event) {
        const target = event.target;
        const modalPreloader = this.modal ? this.modal.querySelector('.modal-wrap').firstElementChild : undefined;

        event.preventDefault();

        if (!target || !target.closest('.modal') || target.name !== 'product-rating') {
            return false;
        }

        if (target.querySelector('.rating')) {
            const rating = target.querySelector('.rating').querySelectorAll('svg.active').length;
            const itemFavoriteId = this.itemFavoriteId;
            // ajax
            this.helperInstance && this.helperInstance.initPreloader(modalPreloader);
            BX.ajax({
                url: this.buildUrlQuery('setUserRating'),
                method: 'POST',
                dataType: 'json',
                data: {
                    id: itemFavoriteId,
                    rating,
                },
                onsuccess: res => {
                    if (typeof res === "string") {
                        res = JSON.parse(res);
                    }

                    window.__debug && console.log(res);
                    this.helperInstance && this.helperInstance.removePreloader(modalPreloader);

                    if (res.hasOwnProperty('status')) {
                        if (res.status === 'error') {
                            this.helperInstance.handleCommonErrors(res.errors);
                        } else {
                            if (this.pageBlock.querySelector('[data-sort-id="' + this.productId + '"]')) {
                                const elementBlock = this.pageBlock.querySelector('[data-sort-id="' + this.productId + '"]');
                                // установить data-element-id
                                if (res.hasOwnProperty('data') && res.data.hasOwnProperty('RECORD_ID') && !elementBlock.dataset.elementId) {
                                    elementBlock.dataset.elementId = res.data['RECORD_ID'];
                                }
                                elementBlock.querySelector('.card-user-rating')
                                    .querySelectorAll('svg').forEach((element, index) => {
                                    element.classList.remove('active')
                                    if (Math.floor(rating) >= (index + 1)) {
                                        element.classList.add('active');
                                    }
                                });
                            }

                            var e = new Event('click', {bubbles: true, cancelable: true});

                            this.modal.querySelector('.submit-block').classList.add('text-center');
                            this.modal.querySelector('.submit-block [type=submit]').hidden = true;

                            var node = document.createElement('div');
                            node.classList.add('mt-20', 'mb-10');
                            node.setAttribute('data-id', 'notify-text');
                            node.innerText = 'Спасибо, ваша оценка успешно установлена.';

                            this.modal.querySelector('.submit-block').appendChild(node);

                            setTimeout(() => {
                                this.modal.querySelector('.submit-block [data-id=notify-text]').remove();
                                this.modal.querySelector('.submit-block [type=submit]').hidden = false;
                                this.modal.querySelector('[data-close]').dispatchEvent(e);
                            }, 1500);

                            // показать успешный notify
                            this.helperInstance.handleNotify('Оценка успешно установлена');
                        }
                    } else {
                        this.helperInstance.handleCommonErrors(['Произошла непредвиденная ошибка. Пожалуйста, попробуйте позже или свяжитесь с нами']);
                    }
                },
                onfailure: error => {
                    this.helperInstance && this.helperInstance.removePreloader(modalPreloader);
                    this.helperInstance.handleCommonErrors(['Произошла непредвиденная ошибка. Пожалуйста, попробуйте позже или свяжитесь с нами']);
                },
            });
        }
    }

    mouseOverOnModal(event) {
        var element = event.target;

        if (!element || !element.closest('.modal') || !element.closest('.rating')) {
            return false;
        }

        if (element && element.closest('svg')) {
            var eventData = {target: element.closest('svg')};
            this.mouseEnterRating(eventData);
        }
    }

    mouseOutOnModal(event) {
        var element = event.target;

        if (!element || !element.closest('.modal') || !element.closest('.rating')) {
            return false;
        }

        var eventData = {target: element.closest('svg')};

        if (!event.relatedTarget || !event.relatedTarget.closest('.stars')) {
            eventData.dropAll = true;
        }

        this.mouseLeaveRatingModal(eventData);
    }

    clickOnModal(event) {
        var element = event.target;

        if (element && element.closest('svg')) {
            var elements = [];

            element.closest('.stars').querySelectorAll('svg').forEach(item => item.classList.remove('active'));
            elements.push(element.closest('svg'));

            var prev = element.closest('svg').previousElementSibling;
            while (prev) {
                elements.push(prev);
                prev = prev.previousElementSibling;
            }

            if (elements.length !== 0) {
                elements.forEach(item => item.classList.add('active'));
            }
        }
    }

    // service
    buildUrlQuery(action, params) {
        var baseUrl = '/bitrix/services/main/ajax.php?';
        var query = {c: 'tf:personal.section.notes', mode: 'ajax'};

        if (!action || action.length === 0) {
            action = 'stub';
        }

        query['action'] = action;

        for (var param in params) {
            if (params.hasOwnProperty(param)) {
                query[param] = params[param];
            }
        }

        return baseUrl + $.param(query, true);
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
};
