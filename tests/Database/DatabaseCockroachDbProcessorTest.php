<?php


use YlsIdeas\CockroachDb\Processor\CockroachDbProcessor;

test('process column listing', function () {
    $processor = new CockroachDbProcessor();

    $listing = [['column_name' => 'id'], ['column_name' => 'name'], ['column_name' => 'email']];
    $expected = ['id', 'name', 'email'];

    $this->assertEquals($expected, $processor->processColumnListing($listing));

    // convert listing to objects to simulate PDO::FETCH_CLASS
    foreach ($listing as &$row) {
        $row = (object) $row;
    }

    $this->assertEquals($expected, $processor->processColumnListing($listing));
});
