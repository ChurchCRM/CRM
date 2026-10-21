/// <reference types="cypress" />

import { runApiGates } from "../../../../support/gate-runner";

runApiGates("finance-no-fundraisers");
