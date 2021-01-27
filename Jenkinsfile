node {
     stage('Checkout') {
        dir('paratest') {
            checkout scm
        }
    }

    stage('Test') {
        dir('dgsecure-driver') {
            //            print "Running unit tests"
            //            TODO: FIX MOCKITO METHOD TOO LARGE ISSUE FOR RestApi.java (File to big)
            //            TODO: Solution: Refactor the class or use partial mocks
            //            sh "./gradlew -x testIntegration test"
            //
            //            withChecks('Unit Tests') {
            //                junit 'build/test-results/**/*.xml'
            //            }

            targetBranch = env.CHANGE_TARGET
            sourceBranch = env.CHANGE_BRANCH

            if (branchName.startsWith("PR-") && targetBranch == "master") {
                echo "Running Integration tests"
                
                withChecks('Integration Tests') {
                    junit 'test/fixtures/results/junit-example-result.xml'
                }
            }
        }
    }
}