<?php

return [

    // Ejecutable de Java 8 que corre el firmador (sri_firma_xml.jar). En Windows/Laragon
    // se puede fijar con SRI_JAVA_PATH; por defecto se usa el "java" del PATH.
    'java_path' => env('SRI_JAVA_PATH', 'java'),

    // Jar que firma el XML con XAdES-BES. Va versionado en el repo (resources/tools) para que viaje con el deploy.
    'firmador_jar' => resource_path('tools/sri_firma_xml.jar'),

    // Carpeta (relativa a storage/app/private) donde se guarda el .p12 de cada empresa.
    'dir_firmas' => 'firmas',

    'timeout_firma' => 60,

    // Web services SOAP del SRI (esquema offline). 1 = pruebas, 2 = producción
    // (mismo valor que empresas.ambiente_sri).
    'endpoints' => [
        1 => [
            'recepcion'    => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline',
            'autorizacion' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline',
        ],
        2 => [
            'recepcion'    => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline',
            'autorizacion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline',
        ],
    ],

    'timeout_ws' => 30,
];
