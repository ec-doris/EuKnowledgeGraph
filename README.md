# Eu Knoweldge Graph configuration

This is based on the offical Wikimedia Deutschland [https://github.com/wmde/wikibase-release-pipeline](repo)

### Run

```
docker-compose -f docker-compose.yml -f docker-compose.extra.yml up
```

### Includes

- Kartographer Extension
- Wikibase Constrains Extension
- Different ad-hoc configurations

### Generate image(wikibase-bundle) and push to ECR

Build image (on Apple Silicon)
```
docker buildx build --platform linux/amd64 . -t 294118183257.dkr.ecr.eu-west-1.amazonaws.com/linkedopendata-wikibase-bundle:1.35.5-wmde.3 -f wikibase-bundle.Dockerfile
```

Login on ECR repository
```
aws ecr get-login-password --region eu-west-1 | docker login --username AWS --password-stdin 294118183257.dkr.ecr.eu-west-1.amazonaws.com
```

Push to repository
```
docker push 294118183257.dkr.ecr.eu-west-1.amazonaws.com/linkedopendata-wikibase-bundle:1.35.5-wmde.3
```

If you change the image tag you need to update the manifest on the gitops repository, 
otherwise you can only delete the pod on the cluster and it will regenerate with the new version
