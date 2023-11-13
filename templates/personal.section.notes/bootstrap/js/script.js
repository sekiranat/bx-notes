import PersonalSectionNotesComponent from "./module";

(function () {
    'use strict';

    // инициализация компонента-класса-модуля
    document.addEventListener('DOMContentLoaded', () => {
        window.PersonalSectionNotesComponent = new PersonalSectionNotesComponent();
        window.PersonalSectionNotesComponent.init();
    });
})();
