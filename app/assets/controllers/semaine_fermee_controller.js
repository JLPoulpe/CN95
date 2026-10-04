import { Controller } from '@hotwired/stimulus';

// N'affiche le champ « Motif » que lorsque la case « Semaine fermée » est cochée.
export default class extends Controller {
    static targets = ['case', 'motif'];

    connect() {
        this.toggle();
    }

    toggle() {
        this.motifTarget.hidden = !this.caseTarget.checked;
    }
}
