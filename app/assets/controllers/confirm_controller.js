import { Controller } from '@hotwired/stimulus';

/*
 * Demande confirmation avant l'envoi d'un formulaire (remplace onsubmit="return confirm(...)", interdit par la CSP).
 * <form data-controller="confirm" data-confirm-message-value="Supprimer ?" data-action="submit->confirm#demander">
 */
export default class extends Controller {
    static values = { message: String };

    demander(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
