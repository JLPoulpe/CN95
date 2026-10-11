import { Controller } from '@hotwired/stimulus';

// Filtre les créneaux par aptitude, côté client : toutes les données sont déjà dans la page.
// Un créneau sans aptitude (data-aptitudes vide) reste toujours visible quel que soit le filtre d'aptitude.
export default class extends Controller {
    static targets = ['aptitudeButton', 'creneau', 'lieu', 'jour', 'vide'];

    aptitude = '';

    selectAptitude(event) {
        this.aptitude = event.currentTarget.dataset.code;
        this.aptitudeButtonTargets.forEach((b) => b.classList.toggle('is-active', b === event.currentTarget));
        this.apply();
    }

    apply() {
        this.creneauTargets.forEach((el) => {
            const aptitudes = el.dataset.aptitudes.split(' ').filter(Boolean);

            el.hidden = !(this.aptitude === '' || aptitudes.length === 0 || aptitudes.includes(this.aptitude));
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
