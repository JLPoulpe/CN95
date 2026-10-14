# Procédures RGPD

Trois procédures : demande de droits d'une personne, violation de données, demande de communication par un tiers.

---

## 1. Demande d'une personne (accès, rectification, effacement, opposition...)

**Délai :** un mois à compter de la réception. Prolongeable de deux mois si la demande est complexe ou nombreuse, à condition d'en informer la personne dans le premier mois.

**Étapes**

1. **Enregistrer** la demande (date, nom, moyen de contact, droit demandé) dans un tableau partagé, ou dans un courrier conservé par le référent.
2. **Vérifier l'identité** de la personne. Demander une pièce d'identité seulement en cas de doute.
3. **Traiter** la demande :
   - *Accès* : export des données de la personne (PDF ou fichier structuré).
   - *Rectification* : correction dans le site et information des sous-traitants concernés.
   - *Effacement* : suppression, sauf si une obligation légale impose de conserver certaines données (à vérifier avec le référent).
   - *Opposition / limitation* : arrêt du traitement concerné.
4. **Répondre** par écrit dans le délai, en indiquant les suites données.
5. **Clôturer** et archiver la demande avec la réponse.

**Modèle de réponse (accès)**

> Bonjour {{prenom}},
>
> En réponse à votre demande du {{date_demande}}, vous trouverez en pièce jointe l'ensemble des données vous concernant conservées par [NOM ASSO] à la date du {{date_reponse}}.
>
> Si vous constatez une erreur, vous pouvez demander une rectification. Si vous souhaitez une suppression, merci de nous l'indiquer.
>
> Cordialement,
> [NOM DU RÉFÉRENT], référent protection des données

**Ce qui ne peut pas être refusé sans motif :** une demande d'accès ou d'effacement est recevable par principe. Un refus doit être motivé et la personne doit être informée de ses voies de recours (CNIL).

---

## 2. Violation de données personnelles

Une violation est tout incident qui détruit, perd, altère, divulgue ou rend accessible des données personnelles : mail envoyé au mauvais destinataire, mot de passe volé, équipement perdu, accès non autorisé au site (guide p. 18).

**Étapes**

1. **Dès la découverte** : isoler l'incident (désactiver un compte, changer les mots de passe concernés, couper l'accès au serveur si nécessaire). Noter l'heure de la découverte.
2. **Documenter** : nature de l'incident, données touchées, nombre de personnes, conséquences probables, mesures prises. Ce document est conservé même si la violation n'est pas notifiée.
3. **Évaluer le risque** pour les personnes : type de violation (confidentialité, intégrité, disponibilité), nature et sensibilité des données, facilité d'identification, gravité des conséquences, caractéristiques des personnes (mineurs notamment), nombre de personnes.
4. **Notifier la CNIL dans les 72 heures** si un risque est possible, via le téléservice de la CNIL. Si le délai est dépassé, indiquer les motifs du retard.
5. **Informer les personnes concernées** sans délai si le risque est élevé (mail direct, texte simple, mesures à prendre).
6. **Corriger** et prévenir la récurrence. Mettre à jour le registre et ce plan.

**Contacts :** le référent RGPD, le président, et le téléservice CNIL (cnil.fr, rubrique *Violations de données personnelles*).

---

## 3. Demande de communication par un tiers

Un tiers peut être une administration, un organisme public, une fédération, une entreprise ou une personne.

**Principe :** ne communiquer que ce qui est prévu par un texte, et seulement le strict nécessaire.

**Étapes**

1. **Exiger une demande écrite** qui cite le texte de loi ou la décision qui l'autorise (guide p. 19).
2. **Vérifier** que le demandeur est bien cité dans ce texte et qu'il est autorisé à recevoir ces informations.
3. **Trier** les données : ne communiquer que celles qui sont nécessaires à l'objectif. Des données anonymisées suffisent souvent (guide p. 13).
4. **Sécuriser** la transmission (remise en main propre, chiffrement, mail chiffré).
5. **Informer** les personnes concernées et leur permettre de s'opposer à la transmission, pour un tiers qui n'a pas de texte l'autorisant (guide p. 13).
6. **Documenter** : copie de la demande, identité de la personne qui répond, périmètre des données transmises, date.

**Cas courants**

| Demandeur | Réponse |
|---|---|
| Commune, à propos d'une subvention | Budget et comptes de l'exercice écoulé uniquement. **Pas de liste nominative des adhérents** (guide p. 19). |
| Sponsor, journaliste, prestataire | Non, sauf accord écrit de chaque personne concernée ou texte légal. |
| Fédération de tutelle | Selon ce que prévoit son affiliation ; à vérifier au cas par cas avec le référent. |
| Administration (justice, police, gendarmerie, fraude) | Oui, sur réquisition ou texte légal ; vérifier le texte. |

---

## 4. Purge annuelle, envoi du mail d'information (exploitation)

### Purge du 31 août

La purge supprime, chaque 31 août, **les comptes utilisateurs et les invitations créés jusqu'à cette date** (et leurs réponses, demandes RGPD et liens avec les responsables). Les comptes administrateurs sont conservés.

Commandes (dans le conteneur `web`) :

| Usage | Commande |
|---|---|
| Voir ce qui serait supprimé | `php bin/console app:rgpd:purger --dry-run` |
| Purge réelle (le 31/08) | `php bin/console app:rgpd:purger` (demande confirmation en mode interactif) |
| Tester avec une autre date | `php bin/console app:rgpd:purger --date=2026-08-31 --dry-run` |
| Exécution exceptionnelle hors 31/08 | `php bin/console app:rgpd:purger --force` (à éviter) |

La commande refuse de s'exécuter hors du 31 août sans `--force`.

Planification sur le serveur (crontab de l'utilisateur qui gère le projet, à adapter au chemin de déploiement) :

```
0 6 31 8 * cd [CHEMIN_DU_PROJET] && docker compose exec -T web php bin/console app:rgpd:purger --no-interaction >> /var/log/cn95-rgpd.log 2>&1
```

Rappels au bureau (J-60, J-30, J-7, J-1) : la commande est lancée chaque jour et n'envoie un mail que les jours de rappel. Elle s'adresse aux comptes BUREAU et ADMIN :

```
0 8 * * * cd [CHEMIN_DU_PROJET] && docker compose exec -T web php bin/console app:rgpd:rappel-purge --no-interaction >> /var/log/cn95-rgpd.log 2>&1
```

Chaque exécution écrit dans le journal du site (`var/log/`) un compteur (aucune donnée personnelle).

**Point d'attention au premier passage** : les comptes et invitations existants ont pour date de création celle de la migration (octobre 2026). Ils seront donc supprimés le 31/08/2027. Un membre inscrit en octobre 2026 sera supprimé le 31/08/2027 même s'il est toujours adhérent. Si cela n'est pas voulu, ne pas oublier de le reprendre avant cette date.

### Envoi du mail d'information aux membres

```
php bin/console app:rgpd:envoyer-information --dry-run
php bin/console app:rgpd:envoyer-information
php bin/console app:rgpd:envoyer-information --representants=parent1@exemple.fr,parent2@exemple.fr
```

- Chaque membre reçoit un lien d'accusé de lecture et deux liens de choix (accepter / refuser les communications facultatives).
- `--representants` indique les adresses des membres mineurs : leur mail est adressé à leur représentant légal.
- Un membre déjà informé pour la version en cours n'est pas renvoyé. Changer la version (`RgpdMailer::VERSION_TEXTE`) pour renvoyer un nouveau texte.
- Les liens ne fonctionnent que si `DEFAULT_URI` est défini dans `.env` (URL publique du site).
