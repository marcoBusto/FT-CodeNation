<?php

require __DIR__ . '/../vendor/autoload.php';

// Los tests corren siempre contra su propia base de datos (nunca la de
// desarrollo/producción) para poder truncar tablas libremente entre tests.
// Ver DatabaseTestCase::setUp(), que además valida en runtime que el nombre
// termine en "_test" como guardarraíl extra.
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3307');
putenv('DB_DATABASE=stock_agricola_test');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');
putenv('JWT_SECRET=secreto-de-test-no-usar-en-produccion');
