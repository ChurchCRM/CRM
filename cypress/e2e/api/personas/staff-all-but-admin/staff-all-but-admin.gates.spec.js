/// <reference types="cypress" />

import { runApiGates } from "../../../../support/gate-runner";

runApiGates("staff-all-but-admin");
