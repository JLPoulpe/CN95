import { Controller } from '@hotwired/stimulus';

// Filtre les créneaux par aptitude côté client : toutes les données sont déjà dans la page.
// Un créneau sans aptitude (data-aptitudes vide) reste toujours visible.
export default class extends Controller {
    static targets = ['button', 'creneau', 'lieu', 'jour', 'vide'];

    select(event) {
        const code = event.currentTarget.dataset.code;

        this.buttonTargets.forEach((b) => b.classList.toggle('is-active', b === event.currentTarget));

        this.creneauTargets.forEach((el) => {
            const codes = el.dataset.aptitudes.split(' ').filter(Boolean);
            el.hidden = code !== '' && codes.length > 0 && !codes.includes(code);
        });

        this.lieuTargets.forEach((el) => {
            el.hidden = !el.querySelector('[data-planning-filter-target="creneau"]:not([hidden])');
        });
        this.jourTargets.forEach((el) => {
            el.hidden = !el.querySelector('[data-planning-filter-target="lieu"]:not([hidden])');
        });

        if (this.hasVideTarget) {
            this.videTarget.hidden = this.jourTargets.some((el) => !el.hidden);
        }
    }
}
