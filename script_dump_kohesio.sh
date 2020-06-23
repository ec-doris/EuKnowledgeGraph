apt-get install jq
#import enviroment variables
source qanswer_secretes.env
export user password
now=`date +"%Y-%m-%d"`

echo "Retriving the key ..."
request=$(curl -X POST 'http://qanswer-core1.univ-st-etienne.fr/api/user/signin' -H 'Content-Type:      application/json' -d '{"usernameOrEmail": "'$user'", "password":"'$password'"}')
echo $request
token=$(echo $request | jq -r '.accessToken')
echo $token

while read -r country id code lang identifier; do
    if [[ ! $country = \#* ]]
	then
		rm /newvolume/dump/kohesio/latest_"$code".xlsx
		echo "x is $country, y is $id"
		curl -G 'https://qanswer-core1.univ-st-etienne.fr/api/endpoint/eu/sparql' --data-urlencode 'timeout=1800'  --data-urlencode 'query=select ?link (SAMPLE(?country_ID) as ?countryID) (GROUP_CONCAT(distinct ?label_en ; separator="|") as ?Name_English) (GROUP_CONCAT(distinct ?label_'$lang' ; separator="|") as ?Name_'$lang') (GROUP_CONCAT(distinct ?countryLabel ; separator="|") as ?Country) (GROUP_CONCAT(distinct ?postalCode ; separator="|") as ?Postal_Code) (GROUP_CONCAT( distinct CONCAT( STR(DAY(?startTime)),"/",STR(MONTH(?startTime)),"/",STR(YEAR(?startTime)))  ; separator="|") as ?Start_Date) (GROUP_CONCAT( distinct CONCAT( STR(DAY(?endTime)),"/",STR(MONTH(?endTime)),"/",STR(YEAR(?endTime))) ; separator="|") as ?End_Date) (GROUP_CONCAT(distinct ?cofinancingRate) as ?Cofinancing_Rate) (GROUP_CONCAT(distinct ?budget) as ?Project_Budget) (GROUP_CONCAT(distinct ?beneficiary ; separator="|") as ?beneficiaries) (GROUP_CONCAT(distinct ?coordinates; separator="|" ) as ?coordinates)  (GROUP_CONCAT(distinct ?catId;  separator="|" ) as ?Category_ID)  (GROUP_CONCAT(distinct ?summary_en ; separator="|") as ?Summary_English) (GROUP_CONCAT(distinct ?summary_'$lang' ; separator="|") as ?Summary_'$lang') where  {?link <https://linkedopendata.eu/prop/direct/P35> <https://linkedopendata.eu/entity/Q9934> . ?link <https://linkedopendata.eu/prop/direct/P32> <https://linkedopendata.eu/entity/'"$id"'> OPTIONAL {?link rdfs:label ?label_en . FILTER (lang(?label_en)="en") } OPTIONAL {?link rdfs:label ?label_'$lang' . FILTER (lang(?label_'$lang')="'$lang'") } OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P32> ?country .?country rdfs:label ?countryLabel .FILTER(lang(?countryLabel)="en")} .  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P460> ?postalCode . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P20> ?startTime . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P33> ?endTime . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P837> ?cofinancingRate . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P474> ?budget . }   OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P841> ?beneficiary . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P836> ?summary_en . FILTER (lang(?summary_en)="en")  } OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P836> ?summary_'$lang' . FILTER (lang(?summary_'$lang')="'$lang'")  } OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P127> ?coordinates }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P888> ?cat .         ?cat <https://linkedopendata.eu/prop/direct/P869> ?catId }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P833> ?czechId . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P891> ?irishId . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P1360> ?italianId . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P842> ?polishId . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P844> ?danishId . }  OPTIONAL { ?link <https://linkedopendata.eu/prop/direct/P843> ?frenchId . } OPTIONAL {?link <https://linkedopendata.eu/prop/direct/'$identifier'> ?country_ID } } group by ?link ' -H "Authorization: Bearer $token" --data-urlencode 'format=csv' > /newvolume/dump/kohesio/"$code"_tmp.csv
		curl -G 'https://qanswer-core1.univ-st-etienne.fr/api/endpoint/eu/sparql' --data-urlencode 'timeout=1800'  --data-urlencode 'query=select ?link ?program  where  {?link <https://linkedopendata.eu/prop/direct/P35> <https://linkedopendata.eu/entity/Q9934> . ?link <https://linkedopendata.eu/prop/direct/P32> <https://linkedopendata.eu/entity/'"$id"'> . ?link <https://linkedopendata.eu/prop/direct/P1368> ?o . ?o <https://linkedopendata.eu/prop/direct/P1367> ?program .} ' -H "Authorization: Bearer $token" --data-urlencode 'format=csv' > /newvolume/dump/kohesio/"$code"_program.csv
		csvjoin --right -c link /newvolume/dump/kohesio/"$code"_program.csv /newvolume/dump/kohesio/"$code"_tmp.csv > /newvolume/dump/kohesio/"$now"_"$code".csv
		cp /newvolume/dump/kohesio/"$now"_"$code".csv /newvolume/dump/kohesio/latest_"$code".csv
		rm /newvolume/dump/kohesio/"$code"_tmp.csv
		rm /newvolume/dump/kohesio/"$code"_program.csv

    fi
done < countries.csv
source venv/bin/activate
python csv2xlsx.py
