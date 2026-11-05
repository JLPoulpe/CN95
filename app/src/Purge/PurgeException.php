<?php

namespace App\Purge;

/** Purge refusée avant toute suppression (niveau inconnu, table non classée, aucun administrateur…). */
final class PurgeException extends \RuntimeException
{
}
