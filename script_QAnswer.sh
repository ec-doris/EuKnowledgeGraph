apt-get install jq
#import enviroment variables
source qanswer_secretes.env 
export user password

#dump the wikibase content
cd extensions/Wikibase/
php repo/maintenance/dumpRdf.php --format "nt" --flavor "full-dump" > dump_full.nt
php repo/maintenance/dumpRdf.php --format "nt" --flavor "truthy-dump" > dump_truthy.nt
cat dump_full.nt | grep "http://schema.org/about\|http://schema.org/inLanguage\|http://schema.org/isPartOf" > wikipedia_links.nt
cat wikipedia_links.nt dump_truthy.nt > dump.nt
#login to QAnswer
echo "Retriving the key ..."
request=$(curl -X POST 'http://qanswer-core1.univ-st-etienne.fr/api/user/signin' -H 'Content-Type:      application/json' -d '{"usernameOrEmail": "'$user'", "password":"'$password'"}')
echo $request
token=$(echo $request | jq -r '.accessToken')
echo $token
#upload and index the dataset to QAnswer
echo "Uploading ..."
curl -X POST 'https://qanswer-core1.univ-st-etienne.fr/api/dataset/upload?dataset=eu' -H "Authorization: Bearer $token" -F file=@dump.nt
echo "Indexing ..."
curl -X POST 'https://qanswer-core1.univ-st-etienne.fr/api/dataset/index?dataset=eu' -H "Authorization: Bearer $token"
