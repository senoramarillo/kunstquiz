<?php

require_once('class.base.php');

class Artwork extends Base {
    
    public function asArray() {

    }
    
}

class ArtworkDao extends BaseDao {

    public static $ArtworksDatabase = '/var/www/backend/db/artworks_database.xml';
    public static $PersonsDatabase = '/var/www/backend/db/persons_database.xml';

    public function GET($verb, $args) {

        if ($verb == 'year') {

            $queryStr = "
				import module namespace r = \"http://www.zorba-xquery.com/modules/random\";
				for \$artworks in doc('/var/www/backend/db/artworks_database.xml')/artworks,
 				  \$persons in doc('/var/www/backend/db/persons_database.xml')/persons
 				let \$artwork := \$artworks/artwork[matches(year/text(), '^[0-9][0-9][0-9][0-9]\$')]
 				let \$rows := count(\$artwork)
-				let \$rand0 := r:random-between(1, \$rows)
-				let \$rand1 := r:random-between(1, \$rows)
-				let \$rand2 := r:random-between(1, \$rows)
-				let \$rand3 := r:random-between(1, \$rows)
 				let \$id := \$artwork[\$rand0]/personID/@ID
 				let \$painter := \$persons/person[personID[@ID=\$id]]/name/text()
            ";

            $query = $this->_zorba->compileQuery($queryStr);
            $result = $query->execute();
            $query->destroy();

            return $result;

        }

        return;
    }
}
