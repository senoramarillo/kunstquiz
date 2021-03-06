# Kunstquiz der Freien Universität Berlin

## Zusammenfassung

Im Rahmen der Veranstaltung "XML-Technologien" am Institut für Informatik der Freien Universität war ein Projekt anzufertigen, in dem durch die Anwendung verschiedener Technologien der Vorlesungsstoff gefestigt werden sollte.
Für die Umsetzung des Projektes wurde etwa ein Monat Zeit gewährt.
Das Projekt sollte sich am Wettbewerb [Coding Da Vinci](http://codingdavinci.de/) orientieren, allerdings war eine offizielle Teilnahme nicht Pflicht.


## Einleitung + Verwendete Daten

Unsere Gruppe hat sich auf die Projektidee "Kunstquiz" geeinigt.
In diesem Projekt entstand eine Webanwendung, in dem der Nutzer 10, 15 oder 20 Fragen auswählen kann und diese dann lösen muss.
Inhalt der Fragen sind Kunstwerke, die ursprünglich ebenfalls eingeplanten Bücher und Gebäude konnten aufgrund der kurzen Zeit leider nicht mehr integriert werden.
Der Benutzer soll aus vier Antwortmöglichkeiten den richtigen Künstler, der das Werk gemalt hat, oder das Jahr, in dem es gemalt wurde, bestimmen.
Die Daten, die wir für das Projekt benötigten, stammen von der [Deutsche Digitale Bibliothek](https://www.deutsche-digitale-bibliothek.de/) und der [DBpedia](http://wiki.dbpedia.org/).

![Schematische Darstellung der Technologien](documents/schema.png?raw=true "Schematische Darstellung der Technologien")

Nachfolgend werden die eingesetzten Technologien genauer vorgestellt

## Eingesetzte Technologien

### LOD-Endpoint + SPARQL

Zunächst wurden von der Deutschen Digitalen Bibliothek geeignete Personen extrahiert.
Um diese Personen mit Daten anzureichern, zu denen man Fragen stellen kann, wurde als LOD-Endpoint die DBpedia ausgewält.
Die Herausforderung hierbei war es, die Relation zwischen `DDB{Person}->DBpedia{Person->Kunstwerke}` herzustellen.
Hier bot es sich an, alle Daten in einer Ausführung auszulesen, sodass die dafür eingesetzten PHP-Skripte erst die Personen aus der DDB extrahieren und anhand dieser Personen die Relationen in der DBPedia ermittelten.
Da die Personen aus der DDB nur als JSON verfügbar waren, wurden auch die Daten aus der DBPedia als JSON ausgelesen.
Um die Daten in XML zu transformieren, wurde der [`XML_Serializer`](https://pear.php.net/package/XML_Serializer) von Pear verwendet.

Genaue Abfolge der Datensammlung:

   1. Personen aus der DDB anhand von ausgewählten Berufen extrahieren.
      Um die DDB verwenden zu können benötig man einen API-Key, den man im HTTP-Header angeben kann.

      Query:
      
      ```
      api.deutsche-digitale-bibliothek.de/entities?rows=10000&query=professionOrOccupation:[Beruf(e)]
      ```

   2. Die wichtigsten Daten des Personen-JSON-Objekts in einem Personen-PHP-Objekt speichern.

   3. Finden der Person in der DBPedia.
      
      Query:
      
      ```
      SELECT ?person,?abstract,?wikilink
      WHERE {
         ?person a dbpedia-owl:Person.
         ?person dbpedia-owl:abstract ?abstract.
         ?person foaf:name ?name.
         ?person prov:wasDerivedFrom ?wikilink.
         FILTER (LANG(?abstract)="de" &&(?name = "'.$person.'"@en || ?name = "'.$person.'"@de)) 
      }
      ```
   4. Zusätzliche Metadaten im entsprechenden Personen-PHP-Objekt speichern.

   5. Anhand des Unique-Identifiers einer Person die zugehörigen Kunstwerke ermitteln.

      Wichtigste Teile der Artwork-Query:
      
      ```
      SELECT ?artwork,?thumbnail,?name,?year,?abstract,?wikilink,?type
      WHERE {
         ?artwork a dbpedia-owl:Artwork.
         /* hier gibt es im Original noch mehr Attribute */
         ?artwork dbpedia-owl:author ?author. 
         ?author prov:wasDerivedFrom ?id.
         /* im Original beginnen hier optionale Attribute */
         FILTER (?id=<'.$personURI.'>)
      }
      LIMIT 1000
      ```

      Es gibt noch Queries für Gebäude und Bücher
      
   6. Extrahierte Daten der Kunstwerke im entsprechenden Personen-PHP-Objekt speichern.

   7. Das Personen-PHP-Objekt in XML transformieren.
      
      Code:
      
      ```php
      function obj_to_xml($obj) {
          $serializer = new XML_Serializer();

          if ($serializer->serialize($obj)) {
              return $serializer->getSerializedData();
          } else {
              return null;
          }
      }
      ```
   
   8. XML-Datei speichern.

Zu jeder Person gab es am Ende eine eigene XML-Datei.
Die weitere Aufarbeitung der Daten wurde dann mit XSLT durchgeführt.

### XML-Schema

Das XML-Schema diente dazu, den Aufbau der Datenbank auf eine einheitliche Form festzulegen.
Die hierfür vorhandenen Daten lassen sich in zwei Gruppen zusammenfassen.
Zum einen in Künstler, Architekten und Autoren, zum anderen in Kunstwerke, Bücher und Gebäude sowie deren zugehörige Details.
Der Zusammenhang zwischen Künstlern und ihren Werken stellt eine 1:N-Beziehung dar.
Daher werden die beiden Gruppen für einen effizienteren Zugriff in einzelne Dateien aufgeteilt und über generierte IDs eindeutig zugeordnet.
Desweiteren wurden Datensätze wie z.B. der dbp-Resource-Link einer Person, die in den Fragen keine Verwendung fanden, nicht übernommen.

### XSLT + XML-Datenbank

Mittels XSLT wurden die Rohdaten transformiert, so dass sie dem erstellen XML-Schema entsprachen.

Als Ausgangsdaten lag zu jedem Künstler je eine einzelne XML-Datei vor, die auch deren Kunstwerke enthielt.
Diese Datein wurden in einem Vorverarbeitungsschritt zu einer großen XML-Datei zusammengefasst.
Nun wurde den Künstlern per XSLT eine eindeutige ID zugewiesen.
Danach wurde für jede Tabelle (Personen, Bilder, Bücher, Architektur) eine XSLT-Transformation erstellt, die die Daten entsprechend der Schemata selektierte und die Kunstwerke über die Personen-ID mit dem entsprechenden Künstler verknüpfte.

Somit entstanden vier XML-Dokumente, welche die XML-Datenbank darstellen.

Beispiel buildings.xslt:
```xml
<?xml version="1.0"?>
<xsl:stylesheet
	version="2.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	
	<xsl:template match="/">
		<buildings>
			<xsl:apply-templates select="/persons/person/buildings/building"/>
		</buildings>
	</xsl:template>
	<xsl:template match="building">
		<building>
			<xsl:copy-of select="../../personID"/>
			<xsl:copy-of select="*"/>
		</building>
	</xsl:template>
</xsl:stylesheet>
```

### REST-API + XQuery

Die Rest-API, welche in PHP entwickelt wurde, liefert über das URL-Muster `http://URL/api/entity/action/args/...` die entsprechenden Daten im JSON-Format.
Dafür wird intern der HTTP Methodentyp (z.B. `GET`) ausgewertet und versucht, auf dem Objekt `Entity` die Funktion `GET` mit den Parameter `action` und weiteren optionalen Parametern `args` aufzurufen.
In diesen Funktionen werden dann die Anfragen an die Datenbank mit XQuerys ausgeführt, welche ein XML-Ergebnis liefern, das dann in ein PHP-Objekt transformiert und in für die Rückgabe in JSON kodiert wird.

```php
class Artwork extends Base {

    public static $Database = '/var/www/backend/db/artworks_database.xml';

    public function GET($verb, $args) {

        if ($verb == 'year') {
            $queryStr = "
                import module namespace r = \"http://www.zorba-xquery.com/modules/random\";
                for \$artworks in doc('".Artwork::$Database."')/artworks,
                    \$persons in doc('".Person::$Database."')/persons
                let \$artwork := \$artworks/artwork[matches(year/text(), '^[0-9][0-9][0-9][0-9]\$')]
                let \$rows := count(\$artwork)
                let \$rand0 := r:random-between(1, \$rows)
                let \$rand1 := r:random-between(1, \$rows)
                let \$rand2 := r:random-between(1, \$rows)
                let \$rand3 := r:random-between(1, \$rows)
                let \$id := \$artwork[\$rand0]/personID/@ID
                let \$painter := \$persons/person[personID[@ID=\$id]]/name/text()
                return
                <q>
                    <question>Aus welchem Jahr stammt dieses Bild?</question>
                    <hint>Das Bild ist von {\$painter}.</hint>
                    <image>{\$artwork[\$rand0]/thumbnail/text()}</image>
                    <answers>
                        <rightAnswer>{\$artwork[\$rand0]/year/text()}</rightAnswer>
                        <wrongAnswer1>{\$artwork[\$rand1]/year/text()}</wrongAnswer1>
                        <wrongAnswer2>{\$artwork[\$rand2]/year/text()}</wrongAnswer2>
                        <wrongAnswer3>{\$artwork[\$rand3]/year/text()}</wrongAnswer3>
                    </answers>
                    <wikilink>{\$artwork[\$rand0]/abstract/text()}</wikilink>
                </q>
            ";
			
            $query = $this->_zorba->compileQuery($queryStr);
            $result = $query->execute();
            $query->destroy();
            return $this->jsonencode($result);
        }
    // ...
    }
// ...
}
```

In obigen Beispiel wird eine Frage generiert, die eine richtige Antwort, drei falsche Antworten, den Künstler und den Wikilink enthält.
Über vier Zufallszahlen wurden dabei sowohl das abzufragende Kunstwerk als auch die drei falschen Antworten zufällig ausgewählt.
So können dann im Frontend zufällige Fragen nach dem Muster `http://URL/question/random` abgerufen werden.
Intern wird dafür zufällig entschieden, welcher Fragetyp ausgewählt wird und die Anfrage beispielsweise an `/antwort/year` weitergeleitet (s.o.).

### Webinterface + semantische Daten

Für die Entwicklung des Webinterfaces wurden PHP, HTML5, Javascript und CSS3 verwendet.
Dabei wurde "Bootstrap 3" als CSS-Framework benutzt und das Webinterface dementsprechend responsiv gestaltet.
Beim Aufruf des Webinterfaces gelangt der Benutzer auf eine Startseite, in der er aus 10, 15 oder 20 Fragen auswählen kann.
Die Fragen werden dann nacheinander angezeigt und dabei wird eine Statistik über richtige und falsche Antworten geführt und unten rechts angezeigt.
Die Fragen werden über die REST-API einzeln vom Server abgerufen und enthalten jeweils immer folgende Felder:

  * die Frage nach dem Titel des Werkes oder der Jahreszahl, aus der es stammt
  * ein Hinweis über das Werk
  * die URL des Bildes
  * die richtige Antwort mit drei weiteren ähnlichen, falschen Antwortmöglichkeiten
  * und der Wikipedia-Link zum Autor des Werkes
	
Der Wikipedia-Link zum Autor wurde in Microformats in das obere rechte Icon aus drei Balken eingebettet.
Die richtige Antwort bleibt beim Server gespeichert und wird beim Client(Browser) nicht angezeigt.
Die vier Antwortmöglichkeiten werden zufällig sortiert und in einem Formular angezeigt.
Die Überprüfung der gewählten Antwort nach der Richtigkeit erfolgt dann über ein AJAX-Aufruf durch jQuery, sodass das Ergebnis sofort angezeigt wird und die Statistik-Anzeige sofort aktulisiert wird.
Am Ende des Quiz wird wieder die Statistik über richtige und falsche Antworten angezeigt.
Der Benutzer kann hier ein neues Spiel starten und die Statistikdaten werden dann zurückgesetzt.

![Screenshot des Quiz](documents/screenshot.jpg?raw=true "Screenshot des Quiz")

## Setup

Die Installation wird durch den Einsatz eines [Docker](https://www.docker.com/)-Containers stark vereinfacht.
Um diese Webanwendung auszuführen, wird ein Webserver (hier Apache), PHP und Zorba benötigt.
Vor allem die Installation von Zorba ist sehr fehleranfällig, da das Zorba-Paket wegen eines fehlerhaften Versionsvergleiches während der Installation auf einigen Distributionen nicht verwendet werden kann,
und da der enthaltene PHP-API-Wrapper PHP-Funktionen enthält, welche von Apache nicht mehr unterstützt werden.
Diese Besonderheiten und weitere Konfigurationen werden durch den Docker-Container abgenommen.

Um den Docker-Container zu erstellen und die Webanwendung automatisch in den Container einzubinden, werden [Docker](https://www.docker.com/) und [Docker-Compose](https://docs.docker.com/compose/) benötigt.
Wurden diese installiert, muss der Benutzer, der den Container starten möchte, außerdem der `docker`-Gruppe hinzugefügt werden.
Außerdem muss mit diesem Repository das `docker-apache-php-zorba` Submodul ausgecheckt werden.
Allgemein kann zum auschecken aller Submodule folgender Befehl verwendet werden.

```
git submodule update --init --recursive
```

Nun kann aus dem `docker`-Verzeichnis heraus der Container gestartet werden.
Dafür müssen nur die beiden nachfolgenden Befehle ausgeführt werden.
Bitte beachten Sie, dass das initiale Aufsetzen eines Containers viel Zeit in Anspruch nehmen kann.

```
docker-compose build
docker-compose up -d
```

Standardmäßig wird der Apache-Webserver aus dem Container an den Port `8081` gebunden.
Dies kann über die Änderung des Ports in der Konfigurationsdatei `docker-compose.yml` angepassst werden.

### Debian Jessie
Sollten Sie Debian Jessie verwenden, führen sie den gesamten Installationsprozess ganz einfach durch folgenden Aufruf aus.
Dafür muss der das Skript jedoch von einem Benutzer aus der `sudoer` Gruppe oder dem Superuser `root` aufgerufen werden.

```
./configure
```

