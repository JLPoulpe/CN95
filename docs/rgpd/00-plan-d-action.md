# Plan d'action RGPD — [NOM ASSO]

Ce document résume ce qu'il reste à faire pour que le site de gestion des plannings et des membres respecte le RGPD. Il s'appuie sur le *Guide de sensibilisation au RGPD pour les associations* de la CNIL (fourni dans `docs/cnil-guide_association.pdf`).

> Ce document n'est pas un avis juridique. Faites-le relire par un référent RGPD ou un juriste avant diffusion aux membres.

## 1. Ce qui est déjà décidé

| Point | Décision |
|---|---|
| Responsable de traitement | Le président de [NOM ASSO] |
| Finalité principale | Gérer les plannings, les aptitudes et les invitations des membres |
| Base légale principale | Exécution de l'adhésion (contrat) et intérêt légitime de l'association |
| Consentement | Demandé uniquement pour les communications non liées au planning (newsletter, sollicitations) |
| Conservation | Purge annuelle le 31 août : comptes et invitations créés jusqu'à cette date supprimés |
| Mineurs | Les mails leur étant destinés sont adressés à leurs représentants légaux |
| Sous-traitant | Brevo (envoi des mails) |

Pourquoi le consentement n'est pas la base du planning : un membre qui refuse ne peut pas être retiré du planning sans conséquence. Le RGPD impose que le consentement soit libre. Pour la gestion courante, l'association informe, elle ne demande pas l'accord.

## 2. Actions internes (à faire avant l'envoi aux membres)

- [ ] Compléter les placeholders `[NOM ASSO]`, `[ADRESSE]`, `[EMAIL CONTACT]`, `[PRÉSIDENT]` dans tous les documents de ce dossier.
- [ ] Désigner un **référent RGPD** (un bénévole). Un DPO n'est pas obligatoire pour une association de cette taille (guide p. 3). Son nom doit figurer dans la politique de confidentialité.
- [ ] Compléter et conserver la fiche de registre (`04-registre-traitements.md`). Le registre est obligatoire dans les faits dès qu'un traitement est récurrent (guide p. 14).
- [ ] Signer le contrat de sous-traitance avec **Brevo** (DPA disponible dans leur espace client). Archiver le document. Vérifier la localisation des données dans leur contrat.
- [ ] Vérifier l'hébergement du serveur du site et le contrat avec l'hébergeur (également sous-traitant).
- [ ] Vérifier que les mots de passe respectent une politique minimale, que chaque bénévole a son propre compte et qu'un compte est désactivé à son départ (guide p. 17).
- [ ] Mettre en place une sauvegarde régulière de la base de données et la tester.
- [ ] Planifier la purge du 31 août sur le serveur (voir `05-procedures.md`, section 4). Vérifier la date au premier passage : elle supprime aussi les comptes récents.
- [ ] Vérifier que les journaux du site (`var/log/`) ne conservent pas d'informations inutiles et qu'ils sont effacés régulièrement.
- [ ] Informer le bureau de la procédure en cas de violation de données (voir `05-procedures.md`).

## 3. Communication externe

- [ ] Publier la politique de confidentialité et les mentions légales (`03-politique-confidentialite.md`) sur le site public du club, s'il existe, et sur la page d'accueil du site de gestion.
- [ ] Ajouter le bloc court (`02-mention-mails-inscription.md`) à tous les mails envoyés par le site : activation de compte, invitation, réponse à une invitation.
- [ ] Envoyer le mail d'information (`01-mail-information-membres.md`) à tous les membres actifs, en précisant le lien vers la politique complète. Pour les mineurs, envoyer la variante « représentants légaux ».
- [ ] **Ne publier aucune donnée nominative** sur le site public ou sur les réseaux : listes de nageurs, plannings nominatifs, noms des bénévoles avec téléphone ou e-mail.
- [ ] **Photos et vidéos** : ne publier une image identifiable qu'avec un accord écrit séparé, conservé par l'association. Pour un mineur, l'accord vient du représentant légal (guide p. 10 : pictogrammes pour les enfants).
- [ ] **Demandes de tiers** : une demande de liste de membres (commune, sponsor, journaliste) doit être écrite, citer un texte qui l'autorise, et être limitée au strict nécessaire. Garder la trace de la demande et de la réponse (guide p. 13 et 19).
- [ ] **Subventions communales** : la commune peut demander le budget et les comptes de l'exercice écoulé, mais pas la liste nominative des adhérents (guide p. 19).

## 4. Services de l'État

| Démarche | Obligatoire ? | Quand | Où |
|---|---|---|---|
| Déclaration préalable à la CNIL | **Non** : depuis le RGPD (2018), c'est à l'association de documenter ses traitements. | — | — |
| Registre des traitements | **Oui** dans les faits (guide p. 14) | Maintenu à jour en continu | Document interne, à présenter à la CNIL sur demande |
| Notification de violation de données à la CNIL | **Oui** si un risque pour les personnes | Dans les **72 h** après la découverte | Téléservice CNIL (notifier-violation-donnees) |
| Information des personnes en cas de risque élevé | **Oui** | Sans délai | Mail aux personnes concernées |
| Réponse aux demandes de droits (accès, rectification, effacement...) | **Oui** | Sous **1 mois** (prolongeable de 2 mois si la demande est complexe) | Courrier ou mail à l'association |
| Désignation d'un DPO | Non pour cette association (guide p. 3) | — | — |
| Demande de la CNIL (contrôle) | Possible | Réponse à fournir rapidement | Le registre et les documents ci-dessus doivent être prêts |

Les contacts utiles sont listés dans la fiche CNIL *Les associations et le RGPD* sur cnil.fr et dans la rubrique *Respecter les droits des personnes* (guide p. 16).

## 5. Ce qui est prévu côté site (développement)

- Pages `/mentions-legales` et `/confidentialite` accessibles sans connexion.
- Bloc de mention d'information ajouté aux mails d'activation, d'invitation et de réponse.
- Page de consentement dédiée aux communications non liées au planning. Par défaut, rien n'est coché. Le refus est aussi simple que l'acceptation.
- Commande de purge `app:rgpd:purger` : chaque 31 août, elle supprime les comptes utilisateurs et les invitations créés jusqu'à cette date. Les administrateurs sont conservés. Elle refuse de s'exécuter hors du 31 août sans `--force`, et `--dry-run` affiche les volumes sans rien supprimer.

## 6. Calendrier suggéré

| Échéance | Action |
|---|---|
| Semaine 1 | Compléter les placeholders, désigner le référent, signer le DPA Brevo |
| Semaine 2 | Mettre en ligne les pages publiques et les mentions dans les mails |
| Semaine 3 | Envoyer le mail d'information aux membres |
| J+30 | Relance à ceux qui n'ont pas répondu. Sans réponse, rien ne change pour le planning. |
| Trimestriel | Revue du registre, des accès et des comptes sortants |
| 31 août de chaque année | Purge automatique (cron) : comptes et invitations créés jusqu'à cette date |

## Références

- Guide CNIL *Sensibilisation au RGPD pour les associations* : `docs/cnil-guide_association.pdf`
- cnil.fr : *Conformité RGPD : comment informer les personnes ?*, *Le registre des activités de traitement*, *Notifier une violation de données*
