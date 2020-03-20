apt-get install jq
#import enviroment variables
source qanswer_secretes.env
export user password

echo "Retriving the key ..."
request=$(curl -X POST 'http://qanswer-core1.univ-st-etienne.fr/api/user/signin' -H 'Content-Type:      application/json' -d '{"usernameOrEmail": "'$user'", "password":"'$password'"}')
echo $request
token=$(echo $request | jq -r '.accessToken')
echo $token


while read -r country id code; do
    if [[ ! $country = \#* ]]
	then
		echo "x is $country, y is $id"
		curl -G 'https://qanswer-core1.univ-st-etienne.fr/api/endpoint/eu/sparql' --data-urlencode 'timeout=600'  --data-urlencode 'query=select ?link (GROUP_CONCAT(distinct ?label ; separator="|") as ?labels) (GROUP_CONCAT(distinct ?countryLabel ; separator="|") as ?countryLabels) (GROUP_CONCAT(distinct ?postalCode ; separator="|") as ?postalCodes) (GROUP_CONCAT( distinct CONCAT( STR(DAY(?startTime)),"/",STR(MONTH(?startTime)),"/",STR(YEAR(?startTime)))  ; separator="|") as ?startDate) (GROUP_CONCAT( distinct CONCAT( STR(DAY(?endTime)),"/",STR(MONTH(?endTime)),"/",STR(YEAR(?endTime))) ; separator="|") as ?endDate) (GROUP_CONCAT(distinct ?cofinancingRate) as ?cofinancingRates) (GROUP_CONCAT(distinct ?budget) as ?budgets) (GROUP_CONCAT(distinct ?beneficiary ; separator="|") as ?beneficiaries) (GROUP_CONCAT(distinct ?coordinates; separator="|" ) as ?coordinatess)  (GROUP_CONCAT(distinct ?catId;  separator="|" ) as ?categoryID)  (GROUP_CONCAT(distinct ?summary ; separator="|") as ?summary)  where  {?link <https://linkedopendata.eu/prop/direct/P35> <https://linkedopendata.eu/entity/Q9934> . ?link <https://linkedopendata.eu/prop/direct/P32> <https://linkedopendata.eu/entity/'"$id"'> OPTIONAL {?link rdfs:label ?label } OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P32> ?country .?country rdfs:label ?countryLabel .FILTER(lang(?countryLabel)="en")} .  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P460> ?postalCode . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P20> ?startTime . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P33> ?endTime . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P837> ?cofinancingRate . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P474> ?budget . }   OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P841> ?beneficiary . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P836> ?summary  }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P127> ?coordinates }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P888> ?cat .         ?cat <https://linkedopendata.eu/prop/direct/P869> ?catId }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P833> ?czechId . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P891> ?irishId . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P1360> ?italianId . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P842> ?polishId . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P844> ?danishId . }  OPTIONAL {?link <https://linkedopendata.eu/prop/direct/P843> ?frenchId . } } group by ?link ' -H "Authorization: Bearer $token" --data-urlencode 'format=csv' > ../dump/kohesio/"$code".csv
    fi
done < countries.csv

