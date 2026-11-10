import { Controller } from '@hotwired/stimulus';

/*
 * Ouvre une boîte de dialogue <dialog> (remplace les scripts en ligne, interdits par la CSP).
 * - Sur un bouton : data-controller="dialogue" data-dialogue-cible-value="id-du-dialog" data-action="click->dialogue#ouvrir"
 * - Sur le <dialog> lui-même : data-controller="dialogue" data-dialogue-ouvert-value="true" (ouverture au chargement)
 */
export default class extends Controller {
    static values = { cible: String, ouvert: Boolean };

    connect() {
        if (this.ouvertValue && this.element instanceof HTMLDialogElement && !this.element.open) {
            this.element.showModal();
        }
    }

    ouvrir() {
        document.getElementById(this.cibleValue)?.showModal();
    }
}
