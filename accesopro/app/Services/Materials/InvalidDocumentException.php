<?php

namespace App\Services\Materials;

use RuntimeException;

/** El documento subido no se puede usar como fuente (sin texto, demasiado largo, ilegible). */
final class InvalidDocumentException extends RuntimeException {}
