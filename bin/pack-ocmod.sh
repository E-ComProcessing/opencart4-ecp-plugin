#!/bin/bash

[ -f ecomprocessing.ocmod.zip] && rm ecomprocessing.ocmod.zip

zip -r ecomprocessing.ocmod.zip admin catalog image system install.json
