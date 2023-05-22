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

Build image (on Apple Silicon)(dev)
```
docker buildx build --platform linux/amd64 . -t 294118183257.dkr.ecr.eu-west-1.amazonaws.com/linkedopendata-wikibase-bundle:1.39.1-wmde.11 -f wikibase-bundle.Dockerfile
```

Build image (on Apple Silicon)(prod)
```
docker buildx build --platform linux/amd64 . -t 550062732140.dkr.ecr.eu-central-1.amazonaws.com/linkedopendata-wikibase-bundle:1.39.1-wmde.11 -f wikibase-bundle.Dockerfile

```

Login on ECR repository(dev)
```
aws ecr get-login-password --region eu-west-1 | docker login --username AWS --password-stdin 294118183257.dkr.ecr.eu-west-1.amazonaws.com
```

Login on ECR repository(prod)
```
aws ecr get-login-password --region eu-central-1 | docker login --username AWS --password-stdin 550062732140.dkr.ecr.eu-central-1.amazonaws.com
```

Push to repository(dev)
```
docker push 294118183257.dkr.ecr.eu-west-1.amazonaws.com/linkedopendata-wikibase-bundle:1.39.1-wmde.11
```

Push to repository(prod)
```
docker push 550062732140.dkr.ecr.eu-central-1.amazonaws.com/linkedopendata-wikibase-bundle:1.39.1-wmde.11
```

If you change the image tag you need to update the manifest on the gitops repository, 
otherwise you can only delete the pod on the cluster and it will regenerate with the new version
