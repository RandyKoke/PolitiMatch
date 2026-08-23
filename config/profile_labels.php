<?php

/**
 * Règles de libellé de profil politique (cahier des charges §10.3).
 *
 * Table déclarative plutôt que des `if` en cascade dans le code : l'objectif
 * est que l'expert politique puisse, à terme, proposer des ajustements de
 * seuils ou de nouveaux profils sans toucher à la logique de calcul
 * (ProfileLabelService). Chaque règle est évaluée dans l'ordre du tableau ;
 * la première dont TOUTES les conditions sont vraies l'emporte. Si une
 * thématique référencée n'a pas de score (aucune réponse exploitable), la
 * condition échoue automatiquement — pas besoin de la prévoir explicitement
 * dans chaque règle.
 *
 * Les scores par thème sont dans [-1.0 ; +1.0] (même normalisation que les
 * axes X/Y : Σ(user_score×weight) / Σ(2×weight)). Le signe dépend du sens
 * littéral des questions de chaque thème (voir ProfileLabelServiceTest pour
 * les valeurs empiriques mesurées sur des profils-types réels).
 *
 * Neutralité : aucun terme péjoratif ("extrême", "radical"...) et aucun
 * libellé ne doit tomber d'une variation mineure de réponse — les seuils
 * (0.5 = position marquée, 0.2 = position mesurée) laissent une zone neutre
 * volontairement large avant qu'un profil "tranché" soit attribué.
 *
 * Registre : tutoiement partout, cible principale 16-25 ans (cahier des
 * charges §3). Toutes les descriptions
 * ci-dessous, statiques comme dynamiques (`narrative` plus bas), doivent
 * rester au tutoiement ; aucune phrase au vouvoiement ne doit s'y glisser
 * (cf. ProfileLabelServiceTest, garde-fou dédié).
 *
 * Ordre des règles : l'Environnement est vérifié en premier, avant les
 * profils économiques/sociaux — choix assumé, pas un hasard d'écriture. Le
 * document expert désigne explicitement cette thématique comme "la
 * préoccupation générationnelle numéro un chez les jeunes Belges" ; un
 * signal environnemental marqué est donc considéré comme suffisamment
 * distinctif pour primer sur le reste.
 *
 * Seuils calibrés empiriquement (cf. commande de calibration exécutée lors
 * du développement) sur deux types de profils : des profils "manuels"
 * construits pour chaque libellé cible, et des profils "partis purs"
 * (réponses = positions d'un même parti sur toutes les questions), qui
 * servent surtout à vérifier qu'aucune combinaison réelle ne produit un
 * libellé absurde ou contradictoire — plusieurs d'entre eux retombent
 * légitimement sur le profil de repli "nuancé", ce qui est le comportement
 * voulu plutôt qu'un défaut : mieux vaut l'absence de libellé tranché qu'un
 * libellé forcé sur des réponses réellement mixtes.
 */

return [

    'themes' => ['Économie', 'Environnement', 'Social', 'Immigration', 'Société'],

    // Formes avec article, utilisées partout où un ou plusieurs noms de
    // thème sont insérés dans un gabarit de liaison (group_templates plus
    // bas) : un thème ne doit jamais apparaître nu, sans article ni
    // préposition, dans une phrase générée.
    'theme_with_article' => [
        'Économie' => "l'Économie",
        'Environnement' => "l'Environnement",
        'Social' => 'le Social',
        'Immigration' => "l'Immigration",
        'Société' => 'la Société',
    ],

    // Les libellés courts restent fidèles à l'axe qu'ils décrivent (jamais
    // traduits en une étiquette gauche/centre/droite), mais chaque
    // description s'ouvre sur ce que ça signifie concrètement plutôt que de
    // se limiter à un mot abstrait isolé, sans culture politique déjà
    // installée pour le décoder. Le vocabulaire d'axe déjà établi ailleurs
    // dans l'app (libéral/interventionniste, conservateur/progressiste, cf.
    // PoliticalAxisChart et PartyDetailView) reste employé dans le corps des
    // descriptions, pour rester cohérent d'une page à l'autre, mais jamais
    // comme mot isolé en tête de libellé.
    'rules' => [
        [
            'label' => 'Écologiste et solidaire',
            'description' => "Le climat et la solidarité sont les deux fils conducteurs de tes réponses. Tu es prêt à soutenir une transition écologique ambitieuse, même exigeante, à condition qu'elle s'accompagne d'un vrai filet de sécurité pour celles et ceux qui en portent le plus le poids.",
            'conditions' => [
                ['theme' => 'Environnement', 'operator' => '>=', 'value' => 0.5],
                ['theme' => 'Social', 'operator' => '>=', 'value' => 0.3],
            ],
        ],
        [
            'label' => 'Écologiste avant tout',
            'description' => "Le climat guide clairement tes réponses, sans que cela t'enferme dans un camp économique précis : qu'il s'agisse de davantage d'intervention de l'État ou de la logique de marché, les deux te semblent capables de servir la transition écologique.",
            'conditions' => [
                ['theme' => 'Environnement', 'operator' => '>=', 'value' => 0.5],
                ['theme' => 'Économie', 'operator' => 'between', 'value' => [-0.2, 0.2]],
            ],
        ],
        [
            'label' => 'Priorité au climat',
            'description' => "La protection de l'environnement et la transition énergétique occupent une place centrale dans tes réponses, au point de peser sur ta façon d'aborder la plupart des autres enjeux.",
            'conditions' => [
                ['theme' => 'Environnement', 'operator' => '>=', 'value' => 0.5],
            ],
        ],
        [
            'label' => 'Protection sociale avant tout',
            'description' => "Salaire minimum revalorisé, fiscalité plus juste, services publics renforcés : ce sont les priorités qui reviennent dans tes réponses, clairement du côté interventionniste sur le plan économique. Sur les questions de société en revanche, tu ne tranches pas nettement d'un côté ou de l'autre.",
            'conditions' => [
                ['theme' => 'Économie', 'operator' => '>=', 'value' => 0.3],
                ['theme' => 'Social', 'operator' => '>=', 'value' => 0.3],
                ['theme' => 'Société', 'operator' => 'between', 'value' => [-0.2, 0.2]],
            ],
        ],
        [
            'label' => 'Protection sociale, société ouverte',
            'description' => "Tu fais clairement confiance à l'État pour réduire les inégalités économiques et sociales, une ligne interventionniste assumée, et cette confiance s'accompagne d'une vraie ouverture aux évolutions de société : égalité des droits, diversité, évolution des mœurs, des positions nettement progressistes.",
            'conditions' => [
                ['theme' => 'Économie', 'operator' => '>=', 'value' => 0.3],
                ['theme' => 'Social', 'operator' => '>=', 'value' => 0.3],
                ['theme' => 'Société', 'operator' => '>=', 'value' => 0.3],
            ],
        ],
        [
            // Chaque moitié du libellé précise son propre objet (liberté
            // ÉCONOMIQUE, immigration MAÎTRISÉE), sans mot abstrait livré
            // seul en suspens sans préciser de quoi il s'agit.
            'label' => 'Liberté économique, immigration maîtrisée',
            'description' => 'Tu fais davantage confiance à l’initiative individuelle qu’à l’État pour faire tourner l’économie, une ligne clairement libérale, moins d’impôts, moins de régulation, et tu défends une politique migratoire plus stricte, avec un contrôle renforcé aux frontières.',
            'conditions' => [
                ['theme' => 'Économie', 'operator' => '<=', 'value' => -0.3],
                ['theme' => 'Immigration', 'operator' => '<=', 'value' => -0.2],
            ],
        ],
        [
            'label' => 'Liberté économique avant tout',
            'description' => "Moins d'impôts et moins d'intervention de l'État dans le marché : ta position économique penche clairement du côté libéral, sans que tu affiches pour autant de position tranchée sur les questions de société.",
            'conditions' => [
                ['theme' => 'Économie', 'operator' => '<=', 'value' => -0.3],
                ['theme' => 'Société', 'operator' => 'between', 'value' => [-0.3, 0.3]],
            ],
        ],
        [
            'label' => 'Ouvert aux évolutions de société',
            'description' => "Égalité des droits, diversité, accueil des personnes migrantes : tes réponses traduisent des positions nettement progressistes sur les questions de société, avec des positions économiques qui restent, elles, plus mesurées.",
            'conditions' => [
                ['theme' => 'Société', 'operator' => '>=', 'value' => 0.4],
                ['theme' => 'Immigration', 'operator' => '>=', 'value' => 0.3],
            ],
        ],
        [
            'label' => 'Au cas par cas',
            'description' => "Rien, dans tes réponses, ne penche fortement d'un côté ou de l'autre : tu sembles évaluer chaque enjeu pour ce qu'il est, plutôt que de suivre une grille de lecture idéologique unique.",
            'conditions' => [
                ['theme' => 'Économie', 'operator' => 'between', 'value' => [-0.2, 0.2]],
                ['theme' => 'Société', 'operator' => 'between', 'value' => [-0.2, 0.2]],
            ],
        ],
    ],

    // Aucune règle ci-dessus ne correspond : profil réel mais non typé par
    // une des combinaisons prévues (ex. positions contrastées d'un thème à
    // l'autre) — description volontairement neutre, pas de caricature.
    'fallback' => [
        'label' => 'Profil politique nuancé',
        'description' => "Tes réponses se distribuent différemment selon les sujets, sans dessiner une ligne unique d'un bout à l'autre du quiz. C'est un résultat courant, et pleinement valide : la diversité des enjeux abordés ne se laisse pas toujours résumer à une seule étiquette.",
    ],

    // Trop peu de thématiques ont un score exploitable (quiz très
    // partiellement répondu, ou beaucoup de questions passées) pour risquer
    // une combinaison de règles qui semblerait confiante sans l'être.
    'insufficient_data' => [
        'label' => 'Profil politique à préciser',
        'description' => 'Il manque encore trop de réponses pour dessiner un profil fiable. Réponds à davantage de questions pour affiner ce résultat.',
    ],

    'low_confidence_suffix' => 'Ce profil est à interpréter avec prudence : peu ou pas de réponses ont été enregistrées sur certaines thématiques (%s).',

    /**
     * Description personnalisée : le libellé et sa description générique
     * ci-dessus restent identiques pour tout le monde ayant les mêmes
     * conditions de règle remplies, pour un public de primo-votants
     * (16-25 ans), ce n'est pas assez pédagogique à lui seul.
     * ProfileLabelService::buildNarrative() complète la description
     * générique par 2 à 5 phrases construites à partir des VRAIS scores par
     * thème de l'utilisateur (jamais un texte générique recopié à
     * l'identique d'un profil à l'autre, même à libellé égal).
     *
     * Structure déclarative volontaire (comme les règles ci-dessus) :
     * éditable sans toucher à ProfileLabelService.
     *
     * Variantes : chaque combinaison thème × direction propose PLUSIEURS
     * phrases COMPLÈTES (pas des fragments assemblés dans un gabarit
     * commun) avec des structures grammaticales différentes : parfois la
     * thématique ouvre la phrase, parfois elle la conclut, parfois elle
     * n'est même pas nommée explicitement (portée par le contenu seul).
     * Objectif : éviter l'effet de liste mécanique d'un même patron "Ta
     * position est marquée sur X : tu défends..." répété pour chaque
     * thématique citée. Le contenu factuel de chaque variante reste
     * strictement identique, seule la formulation change.
     *
     * Choix de la variante à afficher : ProfileLabelService::pickVariant()
     * dérive un index déterministe de l'UUID du quiz_result (jamais un vrai
     * hasard), même utilisateur, même résultat à chaque rechargement de
     * page, sans avoir à stocker quelle variante a été choisie. Deux
     * quiz_result différents avec les mêmes scores obtiennent presque
     * toujours des formulations différentes (l'UUID diffère), ce qui évite
     * aussi que "tout le monde avec le même profil" ne lise exactement le
     * même texte mot pour mot.
     *
     * Seuils d'intensité, mêmes valeurs de référence que les règles
     * ci-dessus (0.4 ≈ position "marquée"/"tranchée", 0.15 ≈ position qui
     * commence à pencher, sans être encore affirmée) :
     * - |score| >= 'marked' ou >= 'moderate' → thématique mentionnée
     *   individuellement (la même formulation n'est pas adoucie selon
     *   l'intensité : chaque variante reste volontairement assez générale
     *   pour rester honnête aux deux niveaux).
     * - |score| < 'moderate' → considérée neutre, jamais présentée comme
     *   une "tendance" qui n'existe pas vraiment.
     * 'tie_epsilon' : écart maximal entre les thématiques les plus marquées
     * pour les considérer "à égalité" plutôt que de trancher arbitrairement
     * laquelle citer en premier.
     */
    'narrative' => [

        'thresholds' => [
            'marked' => 0.4,
            'moderate' => 0.15,
            'tie_epsilon' => 0.06,
        ],

        // Neutralité : chaque variante décrit une position ou une tendance
        // factuelle (ce que l'utilisateur défend, d'après ses réponses),
        // jamais un jugement sur cette position — même garde-fou que les
        // règles ci-dessus (cf. ProfileLabelServiceTest, aucun terme
        // péjoratif ne doit s'y trouver). Tutoiement partout, y compris dans
        // les variantes qui ne s'adressent pas directement au lecteur par
        // "tu" (ex. listes de mots-clés) : aucune ne doit contenir "vous".
        'theme_phrases' => [
            'Économie' => [
                'positive' => [
                    // Thème en position de sujet grammatical plutôt
                    // qu'introduit par "Sur", pour ne pas répéter le même
                    // gabarit "Sur [thématique]" que les autres thèmes
                    // ci-dessous.
                    "L'Économie te trouve clairement du côté de l'action publique : salaire minimum revalorisé, fiscalité plus lourde pour les hauts revenus et les grandes fortunes, services publics renforcés.",
                    "Salaire minimum revalorisé, taxation des grandes fortunes, services publics renforcés : ces mesures reviennent souvent dans tes réponses sur l'Économie.",
                    "Tes réponses sur l'Économie montrent une vraie confiance dans l'action de l'État pour réduire les inégalités, plutôt que de laisser le marché s'en charger seul.",
                ],
                'negative' => [
                    "La responsabilisation individuelle et la modération fiscale guident nettement tes réponses en matière économique, avec un rôle plus restreint laissé à l'État.",
                    "Moins d'impôts, moins d'intervention de l'État dans le marché : voilà ce que retiennent tes réponses sur l'Économie.",
                    "Tu fais davantage confiance à l'initiative individuelle qu'à l'État pour réguler l'économie, une ligne qui ressort clairement de tes réponses.",
                ],
                'neutral' => [
                    "Entre intervention de l'État et logique de marché, tes positions économiques restent équilibrées, sans trancher nettement.",
                    'Ni franchement interventionniste ni franchement libérale : ta position économique reste mesurée.',
                    "L'Économie ne fait pas partie des sujets où tu tranches nettement : tes réponses restent équilibrées entre les deux logiques.",
                ],
            ],
            'Environnement' => [
                'positive' => [
                    "La transition écologique occupe, chez toi, le rang de priorité, y compris quand elle implique des efforts économiques à court terme.",
                    'La transition écologique compte vraiment pour toi : tes réponses vont clairement dans ce sens, même quand elle implique des efforts.',
                    "Priorité au climat, quitte à accepter des contraintes économiques : voilà ce qui ressort de tes réponses sur l'Environnement.",
                ],
                'negative' => [
                    "Face à la transition écologique, tu gardes la prudence, en donnant la priorité à d'autres équilibres économiques ou sociaux.",
                    "Tes réponses montrent une approche prudente de l'écologie : tu préfères ne pas sacrifier l'équilibre économique à l'ambition climatique.",
                    "Prudence et réalisme économique avant l'urgence climatique : c'est la ligne qui domine dans tes réponses sur l'Environnement.",
                ],
                'neutral' => [
                    "Entre ambition climatique et réalisme économique, ta position sur l'environnement reste équilibrée.",
                    "Ambition climatique, réalisme économique : tes réponses ne penchent nettement ni vers l'un ni vers l'autre.",
                    "L'Environnement ne ressort pas comme un sujet tranché pour toi : ta position reste mesurée.",
                ],
            ],
            'Social' => [
                'positive' => [
                    "Renforcer la protection sociale et les services publics compte parmi tes priorités : santé, pensions, logement, enseignement plus accessibles.",
                    "Santé, pensions, logement, enseignement : tu veux les rendre plus accessibles à tous, d'après tes réponses sur le Social.",
                    'Renforcer la solidarité collective plutôt que la réduire : tes réponses sur le Social vont clairement dans ce sens.',
                ],
                'negative' => [
                    "Une réforme garantissant la soutenabilité financière de la protection sociale a clairement ta préférence, plutôt qu'un élargissement de sa couverture.",
                    "Garantir que le système reste finançable sur la durée t'importe plus qu'en étendre la couverture, une ligne claire dans tes réponses sur le Social.",
                    'Soutenabilité budgétaire avant extension des droits sociaux : voilà ce qui ressort de tes réponses sur le Social.',
                ],
                'neutral' => [
                    "Entre solidarité collective et maîtrise budgétaire, tes positions sur le plan social restent mesurées.",
                    'Solidarité collective, maîtrise budgétaire : tes réponses ne tranchent pas nettement entre les deux sur le Social.',
                    'Le Social ne fait pas partie des sujets où tu affiches une position tranchée : tes réponses restent équilibrées.',
                ],
            ],
            'Immigration' => [
                'positive' => [
                    "Une politique migratoire plus ouverte te trouve clairement favorable : accueil renforcé, régularisation facilitée.",
                    "L'accueil et la régularisation des personnes migrantes comptent beaucoup pour toi : tes réponses vont clairement dans ce sens.",
                    "Ouverture, accueil, régularisation : voilà les mots qui résument ta position sur l'Immigration.",
                ],
                'negative' => [
                    "Une ligne migratoire plus stricte a clairement ta préférence : contrôle renforcé, conditions d'accueil plus limitées.",
                    "Contrôle renforcé, conditions d'accueil plus strictes : ces priorités reviennent souvent dans tes réponses sur l'Immigration.",
                    "Tu accordes davantage d'importance au contrôle des flux migratoires qu'à l'ouverture, une ligne claire dans tes réponses.",
                ],
                'neutral' => [
                    "Entre plus d'ouverture et plus de contrôle, tes positions sur l'immigration ne penchent nettement d'aucun côté.",
                    "Ouverture, contrôle : tes réponses sur l'Immigration ne penchent nettement d'aucun côté.",
                    "L'Immigration ne ressort pas comme un sujet tranché pour toi : ta position reste mesurée.",
                ],
            ],
            'Société' => [
                'positive' => [
                    "Des positions nettement progressistes se dégagent de tes réponses sur les questions de société : égalité des droits, diversité, évolution des mœurs.",
                    'Égalité des droits, diversité, évolution des mœurs : ces valeurs reviennent souvent dans tes réponses sur la Société.',
                    "Tes réponses sur la Société traduisent une vraie ouverture aux évolutions sociales récentes plutôt qu'une résistance à celles-ci.",
                ],
                'negative' => [
                    "Des positions plus traditionnelles se dégagent de tes réponses sur les questions de société, avec davantage de prudence face aux évolutions récentes.",
                    "Prudence face aux évolutions sociétales récentes : c'est la ligne qui domine dans tes réponses sur la Société.",
                    'Tes réponses sur la Société traduisent une préférence pour des repères plus établis plutôt que pour une évolution rapide des mœurs.',
                ],
                'neutral' => [
                    "Sans pencher nettement d'un côté, tes positions sur les questions de société restent mesurées.",
                    "Ouverture, tradition : tes réponses sur la Société ne penchent nettement ni vers l'une ni vers l'autre.",
                    'La Société ne ressort pas comme un sujet tranché pour toi : ta position reste mesurée.',
                ],
            ],
        ],

        // Gabarits "de liaison" (listes de thématiques, jamais de contenu
        // factuel par thème) : ceux-ci ne posent pas le même risque de
        // répétition puisqu'ils n'apparaissent qu'une fois par description,
        // mais varient aussi pour rester cohérents d'un profil à l'autre.
        'group_templates' => [

            // Introduit une thématique supplémentaire (au-delà de la
            // première mentionnée) déjà "marquée" — prépendu directement à
            // la phrase complète de cette thématique.
            'connector' => [
                'Autre point marquant :',
                'Ta position est également nette sur un second point :',
                'On note aussi une tendance affirmée de ton côté sur ce point :',
            ],

            // Introduit un groupe de thématiques à égalité au sommet (cf.
            // tie_epsilon) — suivi directement de leurs phrases complètes
            // respectives, l'une après l'autre.
            'tie_intro' => [
                'Plusieurs enjeux ressortent avec une intensité comparable dans tes réponses :',
                'Plusieurs tendances se démarquent à égalité chez toi :',
                'Difficile de départager : plusieurs thématiques ressortent avec la même intensité dans tes réponses :',
            ],

            // %s est ici toujours une forme avec article (cf.
            // theme_with_article ci-dessus, injectée par
            // ProfileLabelService::joinList()), jamais un thème nu : chaque
            // variante l'emploie en position de complément, jamais en tout
            // premier mot de la phrase, pour ne jamais avoir besoin d'y
            // recapitaliser un article.
            'moderate_singular' => [
                "Sans trancher nettement, ta position sur %s reste plus mesurée que sur le reste.",
                "Tu n'affiches pas de position tranchée sur %s : tes réponses y restent plus nuancées.",
                "Difficile de dégager une tendance affirmée de ta part sur %s : ta position y reste plus mesurée que sur le reste.",
            ],
            'moderate_plural' => [
                "Sans trancher nettement, tes positions sur %s restent plus mesurées que sur le reste.",
                "Tu n'affiches pas de position tranchée sur %s : tes réponses y restent plus nuancées.",
                "Difficile de dégager une tendance affirmée de ta part sur %s : tes positions y restent plus mesurées que sur le reste.",
            ],
            'neutral_singular' => [
                "Aucune tendance marquée ne se dégage de tes réponses sur %s.",
                "Sur %s, ta position ne se démarque pas nettement d'un côté ou de l'autre.",
                'Impossible de dégager une tendance claire chez toi sur %s.',
            ],
            'neutral_plural' => [
                "Aucune tendance marquée ne se dégage de tes réponses sur %s.",
                "Sur %s, tes positions ne se démarquent pas nettement d'un côté ou de l'autre.",
                'Impossible de dégager une tendance claire chez toi sur %s.',
            ],
            'all_balanced' => [
                "Sur l'ensemble des thématiques abordées (%s), tes positions restent globalement équilibrées, sans qu'aucune ne se démarque nettement : une façon d'évaluer chaque enjeu au cas par cas plutôt qu'une conviction affirmée dans un sens ou dans l'autre.",
                'Aucune thématique ne ressort vraiment chez toi (%s) : tu abordes chaque enjeu au cas par cas, sans ligne directrice unique.',
                "Tes réponses restent équilibrées sur tous les grands thèmes abordés (%s) : ce n'est pas un manque de conviction, juste une évaluation au cas par cas plutôt qu'une grille de lecture unique.",
            ],

        ],

    ],

];
