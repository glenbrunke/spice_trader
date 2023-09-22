<?php

include('st_settings.php'); // settings has global variables that allow the game to be tuned with default values.
readfile('st_header.html');

// //////////////////////////////////////////////////////
// PROGRAM CONTROL SECTION
// Flow of control of program is managed with a Game Object
// and a gameMode variable passed in the query string.
// //////////////////////////////////////////////////////
// Check for gameMode and gameID to process and active game
// If no gameMode exists, then game will default to a start screen

if (isset($_GET["gameMode"])) {
    $gameMode = test_input($_GET["gameMode"]);  
}

else { $gameMode = ""; }

if (isset($_GET["gameID"])) {
    $currentGameID = test_input($_GET["gameID"]);    
}

else { $currentGameID = ""; }

// NEW is the command to create a new game and save it into the database, if a gameID is received, it is deleted to keep the db clean
if ($gameMode == "NEW"){
        if (doesGameExist($currentGameID) == 1) {
            deleteGameFromDB($currentGameID);
        }
        $currentGame = startNewGame();
        printGameControls($currentGame);
}
// LOCATIONSELECT is the command to update the location of the game and return the user to the spice buy/sell page
else if ($gameMode == "LOCATIONSELECT"){
    $newLocation = test_input($_GET["newLocation"]);
    $currentGame = getGameFromDB($currentGameID);
    if (getLocationName($newLocation) == $currentGame->currentLocation) { #The location didn't change, user likely refreshed their screen, don't perform action.
        printGameControls($currentGame);
    }
    else {
        $currentGame->currentLocation = getLocationName($newLocation);
        $currentGame->gameDayNumber += 1;
        $currentGame->loanAmount = intval($currentGame->loanAmount * (1+$GLOBALS['LOAN_INTEREST_RATE']));
        updateSpicePrices($currentGame);
        saveCurrentGame($currentGame);
    
        $randomEventOccured = checkForRandomEvent();

        if ($randomEventOccured > 0) {
            processRandomEvent($randomEventOccured, $currentGame);
        }
        else {
            printGameControls($currentGame);  
        }
    }
}
// MODIFY is the command to update the size of the Cargo Suspensor
else if ($gameMode == "MODIFY") {
    $currentGame = getGameFromDB($currentGameID);
    $modifyCost = $currentGame->modifyCost;
    $newSlots = $currentGame->newSlots;
    
    if ($modifyCost < $currentGame->cashOnHand) {
        $currentGame->cashOnHand = intval($currentGame->cashOnHand - $modifyCost);
        $currentGame->itemSlots = intval($currentGame->itemSlots + $newSlots);
    }
    
    $currentGame->modifyCost = 0;
    $currentGame->newSlots = 0;
    saveCurrentGame($currentGame);
    printGameControls($currentGame);
} 
// ALLSCORES is the command to show the top 10 and bottom 10 scores
else if ($gameMode == "ALLSCORES") {
    printTopScores(1);
    print "<HR><BR>";
    printBottomScores(1);
    print "<BR><BR><CENTER><A id=\"gameButton\" HREF=\"index.php\"><B>NEW GAME</B></A></CENTER><BR>";
} 
// SARDAUKAR is the command to process the action taken from a random Sardaukar encounter
else if ($gameMode == "SARDAUKAR") {
    $currentGame = getGameFromDB($currentGameID);
    $yourAction = test_input($_GET["yourAction"]);
    print printSardaukarScreen($currentGame, $yourAction);
}
// HOME is the command to return to the main screen to buy and sell spice
else if ($gameMode == "HOME") {
    $currentGame = getGameFromDB($currentGameID);
    printGameControls($currentGame);
}
// BUY is the command to update the game with a spice purchase
else if ($gameMode == "BUY"){
    $currentGame = getGameFromDB($currentGameID);
    $spiceName = test_input($_GET["spiceName"]);
    $buyAmount = test_input($_GET["buyAmount"]);
    
    processBuy($currentGame, $spiceName, $buyAmount);
    printGameControls($currentGame);
}
// SELL is the command to update the game with a spice sale
else if ($gameMode == "SELL"){
    $currentGame = getGameFromDB($currentGameID);
    $spiceName = test_input($_GET["spiceName"]);
    $sellAmount = test_input($_GET["sellAmount"]);
    
    processSell($currentGame, $spiceName, $sellAmount);
    printGameControls($currentGame);
}
// BANK is the command navigate to the bank screen
else if ($gameMode == "BANK"){
    $currentGame = getGameFromDB($currentGameID);
    printBankMenu($currentGame);
}
// DEPOSIT is the command to process a deposit and stay on bank screen
else if ($gameMode == "DEPOSIT"){
    $currentGame = getGameFromDB($currentGameID);
    $amountToDeposit = test_input($_GET["depositAmount"]);
    processDeposit($currentGame, $amountToDeposit);
    printBankMenu($currentGame);
}
// WITHDRAWL is the command to process a withdrawl and stay on bank screen
else if ($gameMode == "WITHDRAWL"){
    $currentGame = getGameFromDB($currentGameID);
    $amountToWithdrawl = test_input($_GET["withdrawlAmount"]);
    processWithdrawl($currentGame, $amountToWithdrawl);
    printBankMenu($currentGame);
}
// JET is the command to show a list of planets to move to
else if ($gameMode == "JET"){
    $currentGame = getGameFromDB($currentGameID);
    print "<h3>Loading your cargo for another planet...</h3>";
    printLocationsList($currentGameID);
}
// LOAN is the command to show the loan shark screen
else if ($gameMode == "LOAN"){
    $currentGame = getGameFromDB($currentGameID);
    printLoanScreen($currentGame);
}
// PAYLOAN is the command to process a loan payment and stay on loan shark screen
else if ($gameMode == "PAYLOAN"){
    $currentGame = getGameFromDB($currentGameID);
    $payAmount = test_input($_GET["payAmount"]);
    processLoanPayment($currentGame, $payAmount);
    printLoanScreen($currentGame);
}
// if no command is received, or it is not understood, we return the user to the start page
else {
    printMainStartPage();
}

// //////////////////////////////////////////////////////
// END OF CONTROL SECTION
// //////////////////////////////////////////////////////

readfile('st_footer.html');

// /////////////////////////////////////////////////////
// VIEW FUNCTIONS
// Most primary view fuctions are contained here
// ////////////////////////////////////////////////////

// printMainStartPage - the opening page of the game, the default page shown if an unknown command is received.
function printMainStartPage() {
    print "Inspired by Drug Wars created in 1984 by John E. Dell for MS DOS. It has been reimagined in the futuristic space world of Frank Herbert's Dune book series.<BR><BR>";
    print "<DIV ID=\"imageContainer\"><IMG WIDTH=\"100%\" SRC=\"img/st01.png\"></DIV><BR><BR>";
    print "This is a game of buying, selling, and avoiding the Sardaukar. The object of the game is to pay off your debt to the loan shark. Then, make as much money as you can in a 1 month period. If you deal too heavily in spices, you might run into the Sardaukar!!<BR><BR>";
    print "The prices of spices per unit are:<BR><BR>";
    print "<CENTER><font color=red>MELANGE  \$15,000-\$30,000</font></CENTER>";
    print "<CENTER><font color=red>SAPHO    \$5,000-\$14,000</font></CENTER>";    
    print "<CENTER><font color=red>LIBAN   \$1,000-\$4,500</font></CENTER>";    
    print "<CENTER><font color=red>SHIGAWIRE   \$300-\$900</font></CENTER>";    
    print "<CENTER><font color=red>ELACCA   \$70-\$250</font></CENTER>";        
    print "<CENTER><font color=red>DRUM SAND   \$10-\$60</font></CENTER><BR>";        
        
    print "<CENTER><A id=\"gameButton\" HREF=\"index.php?gameMode=NEW\"><B>NEW GAME</B></A></CENTER><BR><BR>";
    print "<CENTER><A id=\"gameButton\" HREF=\"index.php?gameMode=ALLSCORES\"><B>ALL TIME HIGH AND LOW SCORES</B></A></CENTER><BR>";
}

// printGameControls - prints the main controls along with the spice buy/sell menu
function printGameControls($currentGame) {
    
    if ($currentGame->gameDayNumber < 31) {
        print "<CENTER><H3>" . $currentGame->currentLocation . "</H3></CENTER>";
        print "Cash: " . numberToMoney($currentGame->cashOnHand) . " <BR> Bank Balance: ". numberToMoney($currentGame->cashInBank) . " <BR> Loan Amount: ". numberToMoney($currentGame->loanAmount) . " <BR> Cargo Suspensor: ". usedItemSlots($currentGame) . "/" . $currentGame->itemSlots . "<BR>";
        print "Day: " . $currentGame->gameDayNumber . " / 30 <BR>";
    
        print "<CENTER><HR>";
        
        if ($currentGame->gameDayNumber == 30) {
            $endLocation = getLocationID($currentGame->currentLocation);
            if ($endLocation == 1) {
                $endLocation = 2;
            }
            else {
                $endLocation = 1;
            }
            print "<A id=\"gameButton\" HREF=\"index.php?gameMode=LOCATIONSELECT&newLocation=". $endLocation . "&gameID=" . $currentGame->gameID . "\">END GAME</A><BR><HR>";            
        }
        else {
            print "<A id=\"gameButton\" HREF=\"index.php?gameMode=JET&gameID=" . $currentGame->gameID . "\">GUILD SHIP</A>&nbsp;&nbsp;&nbsp;";
            print "<A id=\"gameButton\" HREF=\"index.php?gameMode=LOAN&gameID=" . $currentGame->gameID . "\">LOAN SHARK</A>&nbsp;&nbsp;&nbsp;";
            print "<A id=\"gameButton\" HREF=\"index.php?gameMode=BANK&gameID=" . $currentGame->gameID . "\">BANK</A><BR><HR>";
        }
        print "</CENTER>";   
        
        // check to see if there should be a random spice event
        $currentGame = processRandomSpiceEvent($currentGame);
        // update that the random event has already been check, prevents a refresh from re-running random event
        $currentGame->dayOfLastEvent = $currentGame->gameDayNumber;
        
        saveCurrentGame($currentGame);
        
        print "<BR><BR>";
        printSpiceMenu($currentGame);
    
        print "<BR>";
        print "<HR>";
        print "<CENTER><A id=\"gameButton\" HREF=\"index.php?gameID=". $currentGame->gameID . "\">NEW GAME</A></CENTER> ";
    }
    else {
        printGameOverScreen($currentGame);
    }
    
}

// printLoanScreen - loan shark screen to present options to re-pay loan
function printLoanScreen($currentGame) {
    $canPayAll = $currentGame->cashOnHand - $currentGame->loanAmount;
    $canPayHalf = $currentGame->cashOnHand - ($currentGame->loanAmount/2);
    
    print "<h3>Loan Balance: <font color=red>" . numberToMoney($currentGame->loanAmount) . "</font></h3>";
    print "<h3>Cash:<font color=green>" . numberToMoney($currentGame->cashOnHand) . "</font></h3><BR>";

    if ($canPayHalf > 0) {

        if ($currentGame->loanAmount > 100) {
            print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=PAYLOAN&payAmount=half&gameID=" . $currentGame->gameID . "\">PAY HALF</A> | ";
        }
    }
    else if ($currentGame->cashOnHand > 99) {
            print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=PAYLOAN&payAmount=one&gameID=" . $currentGame->gameID . "\">PAY \$100</A> | ";   
    }

    if ($currentGame->loanAmount > 0){    
        if ($canPayAll > 0) {
            print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=PAYLOAN&payAmount=all&gameID=" . $currentGame->gameID . "\">PAY ALL</A> | "; 
        }
    }

    print "<A id=\"gameButton\" HREF=\"index.php?gameMode=HOME&gameID=" . $currentGame->gameID . "\">LEAVE LOAN SHARK</A>";
    print "<BR><BR><DIV ID=\"imageContainer\"><IMG WIDTH=\"100%\" SRC=\"img/st09.png\"></DIV><BR><BR>";  
}

// printBankMenu - View screen for depositing and withdrawling money from your bank account. Money in the bank is safe from seizure by the Sardaukar.
function printBankMenu($currentGame) {
    print "<h3>Bank Balance: <font color=green>" . numberToMoney($currentGame->cashInBank) . "</font></h3>";
    print "<h3>Cash: <font color=green>" . numberToMoney($currentGame->cashOnHand) . "</font></h3><BR>";
    print "<B>DEPOSIT:</B><BR><BR>";
    if ($currentGame->cashOnHand > 99) { print "<A  id=\"gameButton\" HREF=\"index.php?gameMode=DEPOSIT&depositAmount=onehundred&gameID=" . $currentGame->gameID . "\">+ \$100</A>&nbsp;&nbsp;&nbsp; "; }
    if ($currentGame->cashOnHand > 999) { print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=DEPOSIT&depositAmount=onethousand&gameID=" . $currentGame->gameID . "\">+ \$1,000</A>&nbsp;&nbsp;&nbsp;"; }
    if ($currentGame->cashOnHand > 9999) { print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=DEPOSIT&depositAmount=tenthousand&gameID=" . $currentGame->gameID . "\">+ \$10,000</A>&nbsp;&nbsp;&nbsp;"; }
    if ($currentGame->cashOnHand > 1) { print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=DEPOSIT&depositAmount=half&gameID=" . $currentGame->gameID . "\">+ HALF</A>&nbsp;&nbsp;&nbsp;"; }
    if ($currentGame->cashOnHand > 0) { print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=DEPOSIT&depositAmount=all&gameID=" . $currentGame->gameID . "\">+ ALL</A>&nbsp;&nbsp;&nbsp;"; }
    
    print "<BR><BR><B>WITHDRAWL:</B><BR><BR>";
    if ($currentGame->cashInBank > 99) { print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=WITHDRAWL&withdrawlAmount=onehundred&gameID=" . $currentGame->gameID . "\">- \$100</A>&nbsp;&nbsp;&nbsp;"; }
    if ($currentGame->cashInBank > 999) { print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=WITHDRAWL&withdrawlAmount=onethousand&gameID=" . $currentGame->gameID . "\">- \$1,000</A>&nbsp;&nbsp;&nbsp;"; }
    if ($currentGame->cashInBank > 9999) { print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=WITHDRAWL&withdrawlAmount=tenthousand&gameID=" . $currentGame->gameID . "\">- \$10,000</A>&nbsp;&nbsp;&nbsp;"; }
    if ($currentGame->cashInBank > 1) { print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=WITHDRAWL&withdrawlAmount=half&gameID=" . $currentGame->gameID . "\">- HALF</A>&nbsp;&nbsp;&nbsp;"; }
    if ($currentGame->cashInBank > 0) { print "<A id=\"gameButton\"  HREF=\"index.php?gameMode=WITHDRAWL&withdrawlAmount=all&gameID=" . $currentGame->gameID . "\">- ALL</A>&nbsp;&nbsp;&nbsp;"; }
    print "<BR><BR><A id=\"gameButton\" HREF=\"index.php?gameMode=HOME&gameID=" . $currentGame->gameID . "\">LEAVE BANK</A>"; 
    print "<BR><BR><DIV ID=\"imageContainer\"><IMG WIDTH=\"100%\" SRC=\"img/st08.png\"></DIV><BR><BR>";  
    
}

// printSpiceMenu - displays the buy/sell menu for spices along with prices and current inventory
function printSpiceMenu($currentGame) {
    
    foreach ($currentGame->spiceList as $currentSpice) {
        $maxNumberToBuy = howManyCanYouBuy($currentGame, $currentSpice->name);
        $maxNumberToSell = $currentSpice->inventory;
        
        print "<B>" . $currentSpice->name . "</B><BR> Price: " . numberToMoney($currentSpice->price) ." Inventory: " . $currentSpice->inventory . "<br>";
        if ($maxNumberToBuy > 0) {
            print "<A ID=\"littleButton\"  HREF=\"index.php?gameMode=BUY&spiceName=" . $currentSpice->name . "&buyAmount=one&gameID=" . $currentGame->gameID . "\">buy 1</A> ";
        }
        if ($maxNumberToBuy > 1) {
            print "&nbsp;&nbsp;&nbsp; <A ID=\"littleButton\"  HREF=\"index.php?gameMode=BUY&spiceName=" . $currentSpice->name . "&buyAmount=half&gameID=" . $currentGame->gameID . "\">buy half</A> ";
        }
        if ($maxNumberToBuy > 0) {
            print "&nbsp;&nbsp;&nbsp; <A ID=\"littleButton\" HREF=\"index.php?gameMode=BUY&spiceName=" . $currentSpice->name . "&buyAmount=max&gameID=" . $currentGame->gameID . "\">buy max</A>";
        }
        print "<BR><BR>";
        if ($maxNumberToSell > 0) {
            print "<A ID=\"littleButton\"  HREF=\"index.php?gameMode=SELL&spiceName=" . $currentSpice->name . "&sellAmount=one&gameID=" . $currentGame->gameID . "\">sell 1</A> ";
        }
        if ($maxNumberToSell > 1) {
            print "&nbsp;&nbsp;&nbsp; <A ID=\"littleButton\"  HREF=\"index.php?gameMode=SELL&spiceName=" . $currentSpice->name . "&sellAmount=half&gameID=" . $currentGame->gameID . "\">sell half</A> ";
        }
        if ($maxNumberToSell > 0) {
            print "&nbsp;&nbsp;&nbsp; <A ID=\"littleButton\"  HREF=\"index.php?gameMode=SELL&spiceName=" . $currentSpice->name . "&sellAmount=all&gameID=" . $currentGame->gameID . "\">sell all</A>";
        }
        print "<BR><BR>";
    }
}

// printGameOverScreen - prints the user's final score and the top 10 scores for context
function printGameOverScreen($currentGame) {
    $finalScore = intval($currentGame->cashOnHand + $currentGame->cashInBank - $currentGame->loanAmount);
    $finalScoreID = saveFinalScore($finalScore);
    
    
    print "<CENTER><h2>Game Over!</h2></CENTER>";
    print "<BR><BR>";
    print "<CENTER>Final Score:<BR></CENTER>";
    print "<CENTER><h2>" . numberToMoney($finalScore) . "</h2></CENTER>";
    printTopScores($finalScoreID);
    print "<BR><BR><CENTER><A id=\"gameButton\" HREF=\"index.php?gameID=". $currentGame->gameID . "\"><B>NEW GAME</B></A></CENTER><BR>";

    if (doesGameExist($currentGame->gameID) == 1) {
            deleteGameFromDB($currentGame->gameID);
    }
}

// printTopScores - Prints the top 10 scores and dates. If the user's score ID is encountered, the top score place is called out.
function printTopScores ($finalScoreID) {
        $printScoreDBConnection = getDBConnection();
        $printScoreSQL = "SELECT * FROM `stTopScores` ORDER BY `stTopScores`.`finalScore` DESC;";
        $scoreRankCounter = 0;
        $printScoreDBResult = $printScoreDBConnection->query($printScoreSQL); 
        
        print "<CENTER><TABLE>";
        print "<TR><TD COLSPAN=3><CENTER><B>*** TOP 10 SCORES ***</B></TD></TR>";
        print "<TR><TD><CENTER><B>&nbsp;&nbsp;RANK&nbsp;&nbsp;</B></CENTER></TD><TD><CENTER><B>&nbsp;&nbsp;FINAL SCORE&nbsp;&nbsp;</B></CENTER></TD><TD><CENTER><B>&nbsp;&nbsp;DATE&nbsp;&nbsp;</B></CENTER></TD></TR>";

        while($printScoreDBRow = $printScoreDBResult->fetch_assoc()) {
            $scoreRankCounter += 1;
            $scoreID = $printScoreDBRow["scoreID"];
            $finalScoreDB = $printScoreDBRow["finalScore"];
            $scoreDate = $printScoreDBRow["scoreDate"]; 
    
            if ($scoreRankCounter < 11) {
                if ($finalScoreID == $scoreID) {
                    print "<TR><TD COLSPAN=3><CENTER>You made the top score list at number " . $scoreRankCounter . "!!</TD></TR>";
                }
                print "<TR><TD><CENTER>" . $scoreRankCounter . "</CENTER></TD><TD><CENTER>". numberToMoney($finalScoreDB) . "</CENTER></TD><TD><CENTER>" . $scoreDate . "</CENTER></TD></TR>";
            }
        }
        print "</TABLE></CENTER>";
        
        $printScoreDBConnection->close();    
}

// printBottomScores - Prints the bottom 10 scores and dates.
function printBottomScores ($finalScoreID) {
        $printScoreDBConnection = getDBConnection();
        $printScoreSQL = "SELECT * FROM `stTopScores` ORDER BY `stTopScores`.`finalScore` ASC;";
        $scoreRankCounter = 0;
        $printScoreDBResult = $printScoreDBConnection->query($printScoreSQL); 
        
        print "<CENTER><TABLE>";
        print "<TR><TD COLSPAN=3><CENTER><B>*** BOTTOM 10 SCORES ***</B></TD></TR>";
        print "<TR><TD><CENTER><B>&nbsp;&nbsp;RANK&nbsp;&nbsp;</B></CENTER></TD><TD><CENTER><B>&nbsp;&nbsp;FINAL SCORE&nbsp;&nbsp;</B></CENTER></TD><TD><CENTER><B>&nbsp;&nbsp;DATE&nbsp;&nbsp;</B></CENTER></TD></TR>";

        while($printScoreDBRow = $printScoreDBResult->fetch_assoc()) {
            $scoreRankCounter += 1;
            $scoreID = $printScoreDBRow["scoreID"];
            $finalScoreDB = $printScoreDBRow["finalScore"];
            $scoreDate = $printScoreDBRow["scoreDate"]; 
    
            if ($scoreRankCounter < 11) {
                if ($finalScoreID == $scoreID) {
                    print "<TR><TD COLSPAN=3><CENTER>You made the top score list at number " . $scoreRankCounter . "!!</TD></TR>";
                }
                print "<TR><TD><CENTER>" . $scoreRankCounter . "</CENTER></TD><TD><CENTER>". numberToMoney($finalScoreDB) . "</CENTER></TD><TD><CENTER>" . $scoreDate . "</CENTER></TD></TR>";
            }
        }
        print "</TABLE></CENTER>";
        
        $printScoreDBConnection->close();    
}

//printSardaukarScreen - depending on the given action of the user, the action is processes and results are displayed to the user.
function printSardaukarScreen($currentGame, $yourAction) {
    $fightChance = $GLOBALS['CHANCE_WIN_FIGHT'];    
    $runChance = $GLOBALS['CHANCE_TO_RUN'];
    
    if ($yourAction == "run") {
        if (rand(0,100) < $runChance) {
            print "You run away and lose them in an alley, close call...<BR>";
            print "<BR><A id=\"gameButton\" HREF=\"index.php?gameMode=HOME&gameID=" . $currentGame->gameID . "\">OK</A>";  
            print "<BR><BR><DIV ID=\"imageContainer\"><IMG WIDTH=\"100%\" SRC=\"img/st05.png\"></DIV><BR><BR>";
        }
        else {
            print "The Sardaukar catches you and beats you senseless for making him run. You lose all of your stash and half of your cash.<BR>"; 
            print "<BR><A id=\"gameButton\" HREF=\"index.php?gameMode=HOME&gameID=" . $currentGame->gameID . "\">OK</A><BR><BR>";  
            print "<BR><BR><DIV ID=\"imageContainer\"><IMG WIDTH=\"100%\" SRC=\"img/st04.png\"></DIV><BR><BR>";
            $spiceIndex = 0;
            foreach ($currentGame->spiceList as $currentSpice) {
                $currentGame->spiceList[$spiceIndex]->inventory = 0;
                $spiceIndex += 1;
            }
            $currentGame->cashOnHand = intval($currentGame->cashOnHand / 2);
            saveCurrentGame($currentGame);
        }
    }
    else if ($yourAction == "fight") {
        if (rand(0,100) < $fightChance) {
            print "You attack, catching the Sardaukar off guard and win! You get away with your stash and cash. The Sardaukar won't be givng you any more trouble from now on.<BR>";
            print "<BR><A id=\"gameButton\" HREF=\"index.php?gameMode=HOME&gameID=" . $currentGame->gameID . "\">OK</A><BR><BR>";  
            print "<BR><BR><DIV ID=\"imageContainer\"><IMG WIDTH=\"100%\" SRC=\"img/st07.png\"></DIV><BR><BR>";
            $currentGame->sardaukarFlag = 1;
            saveCurrentGame($currentGame);
        }
        else {
            print "You turn and fight get pummeled! You lose all of your spices and half of your cash.<BR>";
            print "<BR><A id=\"gameButton\" HREF=\"index.php?gameMode=HOME&gameID=" . $currentGame->gameID . "\">OK</A><BR><BR>";  
            print "<BR><BR><DIV ID=\"imageContainer\"><IMG WIDTH=\"100%\" SRC=\"img/st04.png\"></DIV><BR><BR>";
            $spiceIndex = 0;
            foreach ($currentGame->spiceList as $currentSpice) {
                $currentGame->spiceList[$spiceIndex]->inventory = 0;
                $spiceIndex += 1;
            }
            $currentGame->cashOnHand = intval($currentGame->cashOnHand / 2);
            saveCurrentGame($currentGame);
        }
    }

  
}

// printSardaukarOptions - user choice screen to either run or fight Sardaukar
function printSardaukarOptions($currentGame) {
    print "A Sardaukar spots you trading spices, and is coming after you. What do you do?<BR><BR>";
    print "<A id=\"gameButton\" HREF=\"index.php?gameMode=SARDAUKAR&yourAction=run&gameID=" . $currentGame->gameID . "\">RUN</A>&nbsp;&nbsp;&nbsp;";
    print "<A id=\"gameButton\" HREF=\"index.php?gameMode=SARDAUKAR&yourAction=fight&gameID=" . $currentGame->gameID . "\">FIGHT</A>";
    print "<BR><BR><DIV ID=\"imageContainer\"><IMG WIDTH=\"100%\" SRC=\"img/st06.png\"></DIV><BR><BR>";
}

// printSuspensorOptions - user choice screen to invest in more items slots (if they have the money) or not.
function printSuspensorOptions($currentGame, $additionalItemSlots) {
    $modifyCost = rand(100,500);
    print "You meet a guy that can modify your spice suspensor to give you " . $additionalItemSlots . " more spaces to carry spices. The cost is " . numberToMoney($modifyCost) . ". What do you do?<BR><BR>";
    if ($currentGame->cashOnHand > $modifyCost) {
        print "<A id=\"gameButton\" HREF=\"index.php?gameMode=MODIFY&gameID=" . $currentGame->gameID . "\">BUY</A>&nbsp;&nbsp;&nbsp; ";
    }

    print "<A  id=\"gameButton\" HREF=\"index.php?gameMode=HOME&gameID=" . $currentGame->gameID . "\">NO THANKS</A>";
    print "<BR><BR><DIV ID=\"imageContainer\"><IMG WIDTH=\"100%\" SRC=\"img/st03.png\"></DIV><BR><BR>";
    $currentGame->newSlots = $additionalItemSlots;
    $currentGame->modifyCost = $modifyCost;
    saveCurrentGame($currentGame);
}

// printLocationsList - view to display locations available for travel
function printLocationsList($currentGameID) {
    $currentGame = getGameFromDB($currentGameID);
    $currentLocationID = getLocationID($currentGame->currentLocation);

    if ($currentLocationID != 1) {  print "<A HREF=\"index.php?gameMode=LOCATIONSELECT&gameID=" . $currentGameID . "&newLocation=1\">Caladan</A><BR>"; } else { print "Caladan<BR>"; }
    if ($currentLocationID != 2) {  print "<A HREF=\"index.php?gameMode=LOCATIONSELECT&gameID=" . $currentGameID . "&newLocation=2\">Arrakis</A><BR>"; } else { print "Arrakis<BR>"; }
    if ($currentLocationID != 3) {  print "<A HREF=\"index.php?gameMode=LOCATIONSELECT&gameID=" . $currentGameID . "&newLocation=3\">Chapterhouse</A><BR>"; } else { print "Chapterhouse<BR>"; }
    if ($currentLocationID != 4) {  print "<A HREF=\"index.php?gameMode=LOCATIONSELECT&gameID=" . $currentGameID . "&newLocation=4\">Tleilax</A><BR>"; } else { print "Tleilax<BR>"; }
    if ($currentLocationID != 5) {  print "<A HREF=\"index.php?gameMode=LOCATIONSELECT&gameID=" . $currentGameID . "&newLocation=5\">Giedi Prime</A><BR>"; } else { print "Giedi Prime<BR>"; }
    if ($currentLocationID != 6) {  print "<A HREF=\"index.php?gameMode=LOCATIONSELECT&gameID=" . $currentGameID . "&newLocation=6\">Ix</A><BR>"; } else { print "Ix<BR>"; }
    print "<BR><A id=\"gameButton\" HREF=\"index.php?gameMode=HOME&gameID=" . $currentGame->gameID . "\">CANCEL</A><BR><BR>";
    print "<BR><DIV ID=\"imageContainer\"><IMG WIDTH=\"100%\" SRC=\"img/st02.png\"></DIV><BR><BR>";
}

// processRandomSpiceEvent - randomly prints a message and updates the price of the affected spice
function processRandomSpiceEvent($currentGame) {
    $chanceOfEvent = $GLOBALS['CHANCE_OF_SPICE_EVENT'];
    $eventIndex = intval(rand(1,8));
    $priceFactor = intval(rand(3,6));
    if ($currentGame->gameDayNumber > $currentGame->dayOfLastEvent) {
        if ($chanceOfEvent > rand(1,100)){
            if ($eventIndex == 1) {
                print "<span id=\"spiceEventMessage\">The Bene Gesserit is hoarding spice, melange prices are soaring!</span><BR>";
                $currentGame->spiceList[getSpiceID("Melange")]->price = intval($currentGame->spiceList[getSpiceID("Melange")]->price * $priceFactor);
                $currentGame->dayOfLastEvent = $currentGame->gameDayNumber;
            }
            else if ($eventIndex == 2) {
                print "<span id=\"spiceEventMessage\">Fremen are buying huge amounts of sapho, prices are rising!</span><BR>";
                $currentGame->spiceList[getSpiceID("Sapho")]->price = intval($currentGame->spiceList[getSpiceID("Sapho")]->price * $priceFactor); 
                $currentGame->dayOfLastEvent = $currentGame->gameDayNumber;
            }
            else if ($eventIndex == 3) {
                print "<span id=\"spiceEventMessage\">Honored Matres are buying liban at rediculous prices!</span><BR>";
                $currentGame->spiceList[getSpiceID("Liban")]->price = intval($currentGame->spiceList[getSpiceID("Liban")]->price * $priceFactor);
                $currentGame->dayOfLastEvent = $currentGame->gameDayNumber;
            }
            else if ($eventIndex == 4) {
                print "<span id=\"spiceEventMessage\">A huge order of shigawire from the emperor is increasing prices!</span><BR>";
                $currentGame->spiceList[getSpiceID("Shigawire")]->price = intval($currentGame->spiceList[getSpiceID("Shigawire")]->price * $priceFactor);
                $currentGame->dayOfLastEvent = $currentGame->gameDayNumber;
            }
            else if ($eventIndex == 5) {
                print "<span id=\"spiceEventMessage\">Elacca is becoming more scarce, prices are sky rocketing!</span><BR>";
                $currentGame->spiceList[getSpiceID("Elacca")]->price = intval($currentGame->spiceList[getSpiceID("Elacca")]->price * $priceFactor);
                $currentGame->dayOfLastEvent = $currentGame->gameDayNumber;
            }
            else if ($eventIndex == 6) {
                print "<span id=\"spiceEventMessage\">Drum sand is now everywhere on Chapterhouse, prices are falling!</span><BR>";
                $currentGame->spiceList[getSpiceID("Drum Sand")]->price = intval($currentGame->spiceList[getSpiceID("Drum Sand")]->price / $priceFactor);
                $currentGame->dayOfLastEvent = $currentGame->gameDayNumber;
            }
            else if ($eventIndex == 7) {
                print "<span id=\"spiceEventMessage\">The Harkonnens have stoppped ordering shigawire, prices are falling!</span><BR>";
                $currentGame->spiceList[getSpiceID("Shigawire")]->price = intval($currentGame->spiceList[getSpiceID("Shigawire")]->price / $priceFactor);
                $currentGame->dayOfLastEvent = $currentGame->gameDayNumber;
            }
            else if ($eventIndex == 8) {
                print "<span id=\"spiceEventMessage\">The market is flooded with liban, prices are way down!</span><BR>";
                $currentGame->spiceList[getSpiceID("Liban")]->price = intval($currentGame->spiceList[getSpiceID("Liban")]->price / $priceFactor); 
                $currentGame->dayOfLastEvent = $currentGame->gameDayNumber;
            }
        }
        
    }
    saveCurrentGame($currentGame);
    return $currentGame;
}

// /////////////////////////////////////////////////////
// END OF VIEW FUNCTIONS
// ////////////////////////////////////////////////////

// /////////////////////////////////////////////////////
// GAME MODEL, LOGIC, AND TOOL FUNCTIONS
// Functions to process game logic and tools for
// processing data.
// ////////////////////////////////////////////////////


// processLoanPayment - process a loan payment and update cash and loan balance
function processLoanPayment($currentGame, $payAmount) {
    $canPayAll = $currentGame->cashOnHand - $currentGame->loanAmount;
    $canPayHalf = $currentGame->cashOnHand - ($currentGame->loanAmount/2);

    if ($payAmount == "half") {
        if ($canPayHalf > 0) {
            $howMuchMoney = $currentGame->loanAmount/2;
            $currentGame->loanAmount = intval($howMuchMoney);
            $currentGame->cashOnHand = intval($currentGame->cashOnHand - $howMuchMoney);
            saveCurrentGame($currentGame);
        }
    }
    else if ($payAmount == "all") {
        if ($canPayAll > 0) {
            $howMuchMoney = $currentGame->loanAmount;
            $currentGame->loanAmount = 0;
            $currentGame->cashOnHand = intval($currentGame->cashOnHand - $howMuchMoney);
            saveCurrentGame($currentGame);
        }       
    }
    else if ($payAmount == "one") {
        if ($currentGame->cashOnHand > 99) {
            $howMuchMoney = 100;
            $currentGame->loanAmount = intval($currentGame->loanAmount - $howMuchMoney);
            $currentGame->cashOnHand = intval($currentGame->cashOnHand - $howMuchMoney);
            saveCurrentGame($currentGame);
        }        
    }
}

// processWithdrawl - process a withdrawl, first check balances, then update with requested amounts
function processWithdrawl($currentGame, $amountToWithdrawl) {
    if ($amountToWithdrawl == "onehundred"){
        if ($currentGame->cashInBank > 99) {
            $currentGame->cashInBank =  $currentGame->cashInBank - 100;
            $currentGame->cashOnHand = $currentGame->cashOnHand + 100;
            saveCurrentGame($currentGame);
        }
    }
    else if ($amountToWithdrawl == "onethousand") {
        if ($currentGame->cashInBank > 999) {
            $currentGame->cashInBank =  $currentGame->cashInBank - 1000;
            $currentGame->cashOnHand = $currentGame->cashOnHand + 1000;
            saveCurrentGame($currentGame);
        }     
    }
    else if ($amountToWithdrawl == "tenthousand") {
        if ($currentGame->cashInBank > 9999) {
            $currentGame->cashInBank =  $currentGame->cashInBank - 10000;
            $currentGame->cashOnHand = $currentGame->cashOnHand + 10000;
            saveCurrentGame($currentGame);
        }       
    }
    else if ($amountToWithdrawl == "half") {
        if ($currentGame->cashInBank > 1) {
            $currentGame->cashInBank =  intval($currentGame->cashInBank/2);
            $currentGame->cashOnHand = intval($currentGame->cashInBank + $currentGame->cashOnHand);
            saveCurrentGame($currentGame);
        }        
    }
    else if ($amountToWithdrawl == "all") {
        if ($currentGame->cashInBank > 0) {
            $currentGame->cashOnHand = $currentGame->cashInBank + $currentGame->cashOnHand;
            $currentGame->cashInBank =  0;
            saveCurrentGame($currentGame);
        }      
    }
}

// processDeposit - process a deposit, first check if cash is on hand, then update balances
function processDeposit($currentGame, $amountToDeposit) {
    
    if ($amountToDeposit == "onehundred"){
        if ($currentGame->cashOnHand > 99) {
            $currentGame->cashOnHand =  $currentGame->cashOnHand - 100;
            $currentGame->cashInBank = $currentGame->cashInBank + 100;
            saveCurrentGame($currentGame);
        }
    }
    else if ($amountToDeposit == "onethousand") {
        if ($currentGame->cashOnHand > 999) {
            $currentGame->cashOnHand =  $currentGame->cashOnHand - 1000;
            $currentGame->cashInBank = $currentGame->cashInBank + 1000;
            saveCurrentGame($currentGame);
        }     
    }
    else if ($amountToDeposit == "tenthousand") {
        if ($currentGame->cashOnHand > 9999) {
            $currentGame->cashOnHand =  $currentGame->cashOnHand - 10000;
            $currentGame->cashInBank = $currentGame->cashInBank + 10000;
            saveCurrentGame($currentGame);
        }       
    }
    else if ($amountToDeposit == "half") {
        if ($currentGame->cashOnHand > 1) {
            $currentGame->cashOnHand =  intval($currentGame->cashOnHand/2);
            $currentGame->cashInBank = intval($currentGame->cashInBank + $currentGame->cashOnHand);
            saveCurrentGame($currentGame);
        }        
    }
    else if ($amountToDeposit == "all") {
        if ($currentGame->cashOnHand > 0) {
            $currentGame->cashInBank = $currentGame->cashInBank + $currentGame->cashOnHand;
            $currentGame->cashOnHand =  0;
            saveCurrentGame($currentGame);
        }      
    }
}

// saveFinalScore - Saves the final score to the database
function saveFinalScore ($finalScore) {
        $scoreExistsFlag = doesFinalScoreExist($finalScore);
        $saveFinalScoreID = 0;
        $userIP = $_SERVER['REMOTE_ADDR'];
        
        if (empty($userIP)) { $userIP = "UNKNOWN"; }
        
        if ($scoreExistsFlag == 0) {
            $saveFinalScoreDBConnection = getDBConnection();
            $saveFinalScoreSQL = "INSERT INTO `stTopScores` (`scoreID`, `finalScore`, `scoreDate`, `ipAddress`) VALUES (NULL, '". $finalScore ."',NOW(), '". $userIP . "');";
            $saveFinalScoreDBResult = $saveFinalScoreDBConnection->query($saveFinalScoreSQL); 
            $saveFinalScoreID = $saveFinalScoreDBConnection -> insert_id;
            $saveFinalScoreDBConnection->close(); 
        }
        
        return $saveFinalScoreID;
}

// doesFinalScoreExist - check to see if final score is already in database. 
// the assumption is that a score on a date is unique, if it's already in there, we don't add a second score.
function doesFinalScoreExist ($finalScore) {
        $checkScoreDBConnection = getDBConnection();
        $scoreDoesExist = 0;
        $checkScoreSQL = "SELECT * FROM `stTopScores` WHERE finalScore=" . $finalScore . ";";
        
        $checkScoreDBResult = $checkScoreDBConnection->query($checkScoreSQL); 

        while($checkScoreDBRow = $checkScoreDBResult->fetch_assoc()) {
            $scoreID = $checkScoreDBRow["scoreID"];
            $finalScoreDB = $checkScoreDBRow["finalScore"];
            $scoreDate = $checkScoreDBRow["scoreDate"];  
        }
        
        $todaysDate = date("Y-m-d");
        if ($todaysDate == $scoreDate) {
            if ($finalScoreDB == $finalScore) {
                        $scoreDoesExist = 1; //if the same score exists on today's date, then we assume that it is already there and shouldn't be duplicated.
            }
        }

        $checkScoreDBConnection->close();    
        
        return $scoreDoesExist;
}

// processSell - update the game with a spice sale, check for inventory and update cash
function processSell($currentGame, $spiceName, $sellAmount) {
    if ($sellAmount == "one") {
        if ($currentGame->spiceList[getSpiceID($spiceName)]->inventory > 0) {
            $currentGame->spiceList[getSpiceID($spiceName)]->inventory = $currentGame->spiceList[getSpiceID($spiceName)]->inventory - 1;
            $currentGame->cashOnHand = $currentGame->cashOnHand + $currentGame->spiceList[getSpiceID($spiceName)]->price;
        }
    }
    else if ($sellAmount == "half") {
        if ($currentGame->spiceList[getSpiceID($spiceName)]->inventory > 1) {
            $currentGame->cashOnHand = $currentGame->cashOnHand + ($currentGame->spiceList[getSpiceID($spiceName)]->price * intval($currentGame->spiceList[getSpiceID($spiceName)]->inventory/2));
            $currentGame->spiceList[getSpiceID($spiceName)]->inventory = $currentGame->spiceList[getSpiceID($spiceName)]->inventory - intval($currentGame->spiceList[getSpiceID($spiceName)]->inventory/2);

        }
    }
    else if ($sellAmount == "all") {
        if ($currentGame->spiceList[getSpiceID($spiceName)]->inventory > 0) {
            $currentGame->cashOnHand = $currentGame->cashOnHand + ($currentGame->spiceList[getSpiceID($spiceName)]->price * $currentGame->spiceList[getSpiceID($spiceName)]->inventory);
            $currentGame->spiceList[getSpiceID($spiceName)]->inventory = 0;
        }
    }
    saveCurrentGame($currentGame);
}

// processBuy - update the game with a spice purchase, check for sufficient money and inventory levels
function processBuy($currentGame, $spiceName, $buyAmount) {
    $maxNumberToBuy = howManyCanYouBuy($currentGame, $spiceName);
    
    if ($buyAmount == "one") {
        if ($maxNumberToBuy > 0) {
            $currentGame->spiceList[getSpiceID($spiceName)]->inventory += 1;
            $currentGame->cashOnHand = $currentGame->cashOnHand - $currentGame->spiceList[getSpiceID($spiceName)]->price;
        }
    }
    else if ($buyAmount == "half") {
        if ($maxNumberToBuy > 1) {
            $currentGame->spiceList[getSpiceID($spiceName)]->inventory = $currentGame->spiceList[getSpiceID($spiceName)]->inventory + intval($maxNumberToBuy/2);
            $currentGame->cashOnHand = $currentGame->cashOnHand - ($currentGame->spiceList[getSpiceID($spiceName)]->price * intval($maxNumberToBuy/2));

        }
    }
    else if ($buyAmount == "max") {
        if ($maxNumberToBuy > 0) {
            $currentGame->spiceList[getSpiceID($spiceName)]->inventory = $currentGame->spiceList[getSpiceID($spiceName)]->inventory + intval($maxNumberToBuy);
            $currentGame->cashOnHand = $currentGame->cashOnHand - ($currentGame->spiceList[getSpiceID($spiceName)]->price * $maxNumberToBuy);
        }
    }
    saveCurrentGame($currentGame);    
}

// checkForRandomEvent - randomly interrupt game play between days with events.
// returns the index of the event which is interpreted by processRandomSpiceEvent
function checkForRandomEvent() {
    $returnRandomEvent = 0;
    
    $randomEventFrequency = 85; // how frequent will an event occur? if 70, frequency is 30% of time. if 40, frequency is 60% of time.
    $randomNumberToTest = rand(0,100);

    if ($randomNumberToTest > $randomEventFrequency) {
            $randomIndex = rand(0,6);
            $returnRandomEvent = $randomIndex;
    }
    
    return $returnRandomEvent;
}

// processRandomSpiceEvent - translates the index from checkForRandomEvent into an event
// calls correct event view based on event index
function processRandomEvent($eventIndex, $currentGame) {
    if ($currentGame->sardaukarFlag == 1) { // if the user has previously won the Sardaukar fight, then only process item events
        printSuspensorOptions($currentGame, rand(5,20));
    }
    else {
        if ($eventIndex == 0) {printSardaukarOptions($currentGame);}
        if ($eventIndex == 1) {printSardaukarOptions($currentGame);}
        if ($eventIndex == 2) {printSardaukarOptions($currentGame);}
        if ($eventIndex == 3) {printSardaukarOptions($currentGame);}
        if ($eventIndex == 4) {printSardaukarOptions($currentGame);}
        if ($eventIndex == 5) {printSuspensorOptions($currentGame, rand(5,20));}
        if ($eventIndex == 6) {printSuspensorOptions($currentGame, rand(5,20));}
    }
}

//usedItemSlots - returns the total number of slots in use for spice storage.
function usedItemSlots($currentGame) {
    $itemSlotsReturn = 0;
    
    foreach ($currentGame->spiceList as $currentSpice) {
        $itemSlotsReturn += $currentSpice->inventory;
    }
    return $itemSlotsReturn;
}

//getLocationID - returns the location number based on location string passed to function
function getLocationID($myLocation) {
    
    $returnedLocationID = 0; //default to 0 in case none is found
    
    if ($myLocation == "Caladan") {
          $returnedLocationID = 1; 
    }
    elseif ($myLocation == "Arrakis") {
          $returnedLocationID = 2; 
    }
    elseif ($myLocation == "Chapterhouse") {
          $returnedLocationID = 3; 
    }
    elseif ($myLocation == "Tleilax") {
          $returnedLocationID = 4; 
    }
    elseif ($myLocation == "Giedi Prime") {
          $returnedLocationID = 5; 
    }
    elseif ($myLocation == "Ix") {
          $returnedLocationID = 6; 
    }
   return $returnedLocationID; 
}

//getLocationID - returns the location name based on location index passed to function
function getLocationName($myLocationID) {
    
    $returnedLocationName = "Unknown"; //default to Unknown in case none is found
    
    if ($myLocationID == "1") {
          $returnedLocationName = "Caladan"; 
    }
    elseif ($myLocationID == "2") {
          $returnedLocationName = "Arrakis"; 
    }
    elseif ($myLocationID == "3") {
          $returnedLocationName = "Chapterhouse"; 
    }
    elseif ($myLocationID == "4") {
          $returnedLocationName = "Tleilax"; 
    }
    elseif ($myLocationID == "5") {
          $returnedLocationName = "Giedi Prime"; 
    }
    elseif ($myLocationID == "6") {
          $returnedLocationName = "Ix"; 
    }
   return $returnedLocationName; 
}

//spiceListToCSV - turns the array of spices and their prices and inventory into a CSV to store in database
// returns the CSV string, always a single line
function spiceListToCSV($spiceListToConvert) {
    $spiceListCSV  = "";
    $spiceIndex = 0;
    
    foreach ($spiceListToConvert as $currentSpice) {
        $spiceListCSV = $spiceListCSV . $spiceIndex . "," . $currentSpice->price . "," . $currentSpice->inventory . ",";
        $spiceIndex += 1; 
    }
    $spiceListCSV = substr($spiceListCSV,0,strlen($spiceListCSV)-1); //remove the last comma
    return $spiceListCSV;
    
}

//updateSpicePrices - updates the price of the spices in the current game object
// returns the current, updated game object.
function updateSpicePrices($currentGame) {
    $spiceIndex = 0;
    $tempSpiceList = getNewSpiceList();
    
    foreach ($currentGame->spiceList as $currentSpice) {
        $currentSpice->price = $tempSpiceList[$spiceIndex]->price;
        $spiceIndex += 1; 
    }
    return $currentGame;
}

//getNewSpiceList - generates an array of spice objects with random prices and zero inventory
// returns the array of objects
function getNewSpiceList() {
    
    $listOfSpices[0] = new Spice(rand(15000,30000), "Melange", 0);
    $listOfSpices[1] = new Spice(rand(5000,14000), "Sapho", 0);
    $listOfSpices[2] = new Spice(rand(1000,4500), "Liban", 0);
    $listOfSpices[3] = new Spice(rand(300,900), "Shigawire", 0);
    $listOfSpices[4] = new Spice(rand(70,250), "Elacca", 0);
    $listOfSpices[5] = new Spice(rand(10,60), "Drum Sand", 0);
    
    return $listOfSpices;
    
}

// howManyCanYouBuy - checks to see if you have any available items slots and engough money to buy a given spice
// returns the max number of spices that you can buy of the given type
function howManyCanYouBuy($currentGame, $currentSpiceName) {
    $returnMaxToBuy = 0;
    $availableSlots = 0;
    
    foreach ($currentGame->spiceList as $currentSpice) {
        if ($currentSpice->name == $currentSpiceName) {
            $returnMaxToBuy = intval($currentGame->cashOnHand / $currentSpice->price);
        }
    }
    $availableSlots = $currentGame->itemSlots - usedItemSlots($currentGame);
    
    if ($availableSlots > $returnMaxToBuy) {
        //do nothing, plenty of slots available
    }
    else {
        $returnMaxToBuy = $availableSlots; //return the number of empty slots
    }
    return $returnMaxToBuy;
}

//numberToMoney - converts a given string to a string in money format. Only works for integer values. Can handle negative values.
function numberToMoney ($numberToConvert) {
    // Remove any non-digit characters and convert to an integer
    $numberToConvert = (int) preg_replace('/[^0-9-]/', '', $numberToConvert);

    // Check if the number is negative
    $isNegative = ($numberToConvert < 0);

    // Convert the absolute value to a formatted string
    $formattedValue = number_format(abs($numberToConvert));

    // Add a dollar sign and handle negative numbers
    $formattedMoney = ($isNegative ? '-' : '') . '$' . $formattedValue;

    return $formattedMoney;
}

// getSpiceListFromCSV turns a list of spices in CSV format into an array of Spice objects
// returns Spice object array
// CSV format is "Name,Price,Inventory,Name,Price,Inventory..."
function getSpiceListFromCSV($spiceListCSV) {
    
    $spiceArray = str_getcsv($spiceListCSV); //break CSV string into separate array entries
    $tempSpiceList = getNewSpiceList(); // create the array of spice objects
    
    $x = 0;
    $y = 0;
    $spiceListIndex = 0;
   
   // the array and CSV are arranged in groups of 3 (1=name, 2=price, 3=inventory)
   // this loop loops through the array and breaks the total array into spice objects
   // because the array of spice objects is already created above, we need only to update
   // the price and inventory.
    foreach ($spiceArray as $spiceEntry) {
        if ($x == $y) { //if they are equal, then a new entry has been reached.
            $tempSpiceList[$spiceListIndex]->setPrice($spiceArray[$x+1]);
            $tempSpiceList[$spiceListIndex]->setInventory($spiceArray[$x+2]);
            $x +=3;
            $spiceListIndex +=1;
        }
        else {
            $y += 1;
        }

    }
    // set the last entry
    $tempSpiceList[$spiceListIndex]->setPrice($spiceArray[$x+1]);
    $tempSpiceList[$spiceListIndex]->setInventory($spiceArray[$x+2]);

    return $tempSpiceList;
}

// test_input - escape HTML and ensure data is clean before processing with program.
function test_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

// getDBConnection - returns a connection to the game database for use by other functions
function getDBConnection () {
        // Load database parameter from settings file
        $servername = $GLOBALS['DB_SERVER'];
        $username = $GLOBALS['DB_USER'];
        $password = $GLOBALS['DB_PASSWORD'];
        $dbname = $GLOBALS['DB_DATABASE_NAME'];

        // Create connection
        $dbConnection = new mysqli($servername, $username, $password, $dbname);
        // Check connection
        if ($dbConnection->connect_error) {
            die("Connection failed: " . $dbConnection->connect_error);
        } 

        return $dbConnection;
}

// saveCurrentGame - takes the current game object and saves the data to the database to maintain game state
function saveCurrentGame($currentGame) {
        $saveGameSpiceInventory = spiceListToCSV($currentGame->spiceList);
        $saveGameDBConnection = getDBConnection();
        if ($saveGameDBConnection->connect_error) {
            die("Connection failed: " . $saveGameDBConnection->connect_error);
        } 
        $saveGameSQL = "UPDATE `stGame` SET `spiceInventory` = '". $saveGameSpiceInventory . "',`modifyCost` = '". $currentGame->modifyCost . "',`newSlots` = '" . $currentGame->newSlots . "',`dayOfLastEvent` = '". $currentGame->dayOfLastEvent . "',`gameDayNumber` = '". $currentGame->gameDayNumber . "',`sardaukarFlag` = '". $currentGame->sardaukarFlag . "',`itemSlots` = '". $currentGame->itemSlots . "',`loanAmount` = '". $currentGame->loanAmount . "',`cashInBank` = '". $currentGame->cashInBank . "',`cashOnHand` = '". $currentGame->cashOnHand . "',`currentLocation` = '". $currentGame->currentLocation . "' WHERE `stGame`.`gameID` = " . $currentGame->gameID . ";";
        $saveGameDBResult = $saveGameDBConnection->query($saveGameSQL); 
        $saveGameDBConnection->close();
}

// startNewGame - creates a new game in the database and returns a game object with default values.
function startNewGame() {

        //get first set of spice prices
        $newGameSpiceList = getNewSpiceList();
        $newGameInventory = spiceListToCSV($newGameSpiceList);
        

        $newGameDBConnection = getDBConnection();
        $newGameSQL = "INSERT INTO `stGame` (`gameID`, `cashOnHand`, `cashInBank`, `loanAmount`, `itemSlots`, `sardaukarFlag`, `newSlots`, `modifyCost`, `currentLocation`, `gameDayNumber`, `spiceInventory`, `dayOfLastEvent`) VALUES (NULL, '". $GLOBALS['DEFAULT_START_CASH'] . "','0', '" . $GLOBALS['DEFAULT_START_LOAN'] . "', '" . $GLOBALS['DEFAULT_ITEM_SLOTS'] . "', '0', '0', '0', '" . $GLOBALS['DEFAULT_START_LOCATION'] . "', '1','" . $newGameInventory . "','0');";
        $newGameDBResult = $newGameDBConnection->query($newGameSQL); 
        $newGameID = $newGameDBConnection -> insert_id;
        $newGameDBConnection->close(); 
        $createdGame = new Game($newGameID, $GLOBALS['DEFAULT_START_CASH'], 0, $GLOBALS['DEFAULT_START_LOAN'], $GLOBALS['DEFAULT_ITEM_SLOTS'], 0, 0, 0, $GLOBALS['DEFAULT_START_LOCATION'], 1, $newGameSpiceList,0); 

        return $createdGame;
}

// getGameFromDB returns a Game object from the passed gameID from the database.
function getGameFromDB($currentGameID) {

        $getGameDBConnection = getDBConnection();
        $getGameSQL = "SELECT * FROM `stGame` WHERE gameID=" . $currentGameID . ";";
        
        $getGameDBResult = $getGameDBConnection->query($getGameSQL); 

        while($getGameFromDBRow = $getGameDBResult->fetch_assoc()) {
            $getCashOnHand = $getGameFromDBRow["cashOnHand"];
            $getCashInBank = $getGameFromDBRow["cashInBank"];
            $getLoanAmount = $getGameFromDBRow["loanAmount"];  
            $getItemSlots = $getGameFromDBRow["itemSlots"];  
            $getSardaukarFlag = $getGameFromDBRow["sardaukarFlag"]; 
            $getCurrentLocation = $getGameFromDBRow["currentLocation"]; 
            $getGameDayNumber = $getGameFromDBRow["gameDayNumber"]; 
            $getDayOfLastEvent = $getGameFromDBRow["dayOfLastEvent"];
            $getModifyCost = $getGameFromDBRow["modifyCost"]; 
            $getNewSlots = $getGameFromDBRow["newSlots"]; 
            $getSpiceInventoryCSV = $getGameFromDBRow["spiceInventory"]; 

        }
        
        $getSpiceInventoryList = getSpiceListFromCSV($getSpiceInventoryCSV);
        $getGameDBConnection->close();    
        
        $createdGameFromDB = new Game($currentGameID, $getCashOnHand, $getCashInBank, $getLoanAmount, $getItemSlots, $getSardaukarFlag, $getNewSlots, $getModifyCost, $getCurrentLocation, $getGameDayNumber, $getSpiceInventoryList,$getDayOfLastEvent);       

        return $createdGameFromDB;
}

// deleteGameFromDB attempts to delete a game from the database based on the passed gameID
function deleteGameFromDB ($gameID) {
    
    if (doesGameExist($gameID) == 1) {
        $deleteGameConnection = getDBConnection();
        $deleteGameSQL = "DELETE FROM `stGame` WHERE gameID =". $gameID . ";";
        $deleteGameDBResult = $deleteGameConnection->query($deleteGameSQL); 
        $deleteGameConnection->close();
    }
}

// doesGameExist checks to see if a row is returned for a given gameID
// returns 1 if game exists and 0 if game does not exist
function doesGameExist ($gameID) {
        $doesGameExistConnection = getDBConnection();
        $doesGameExistFlag = 0;

    if ($gameID > 0) {
        $doesGameExistSQL = "SELECT * FROM `stGame` WHERE gameID=" . $gameID . ";";
        $doesGameExistResult = $doesGameExistConnection->query($doesGameExistSQL); 

        while($doesGameExistRow = $doesGameExistResult->fetch_assoc()) {
            $gameIDDB = $doesGameExistRow["gameID"];
        }
        
        if ($gameIDDB == $gameID) {
            $doesGameExistFlag = 1; 
        }
    }

        $doesGameExistConnection->close();  
        return $doesGameExistFlag;
}

// getSpiceID returns the numerical value for a given spice name
function getSpiceID($spiceName) {
    $returnedSpiceID = 0;
    $spiceIndex = 0;
    $tempSpiceList = getNewSpiceList();
    
    foreach ($tempSpiceList as $currentSpice) {
        if ($currentSpice->name == $spiceName) {
            $returnedSpiceID = $spiceIndex;
        }
        $spiceIndex += 1;
    }
    
    return $returnedSpiceID;
}

// /////////////////////////////////////////////////////
// GAME OBJECTS
// Ojbects to enable game flow
// ////////////////////////////////////////////////////

//class Game
// Contains the basic game data making the flow of the game
// easier to manage.
class Game {
    public $gameID;
    public $cashOnHand;
    public $cashInBank;
    public $loanAmount;
    public $itemSlots;
    public $sardaukarFlag;
    public $newSlots;
    public $modifyCost;
    public $currentLocation;
    public $gameDayNumber;
    public $dayOfLastEvent;
    public $spiceList;

    
    public function __construct($gameID, $cashOnHand, $cashInBank, $loanAmount, $itemSlots, $sardaukarFlag, $newSlots, $modifyCost, $currentLocation, $gameDayNumber, $spiceList, $dayOfLastEvent) {
        $this->gameID = $gameID;
        $this->cashOnHand = $cashOnHand;
        $this->cashInBank = $cashInBank;
        $this->loanAmount = $loanAmount;
        $this->itemSlots = $itemSlots;
        $this->sardaukarFlag = $sardaukarFlag;
        $this->newSlots = $newSlots;
        $this->modifyCost = $modifyCost;
        $this->currentLocation = $currentLocation;
        $this->gameDayNumber = $gameDayNumber;
        $this->dayOfLastEvent = $dayOfLastEvent;
        $this->spiceList = $spiceList;
    }
    
}

//class Spice
// A simple object to contain the basic information for a Spice
class Spice {
    public $price;
    public $name;
    public $inventory;
    
    public function __construct($price, $name, $inventory) {
        $this->price = $price;
        $this->name = $name;
        $this->inventory = $inventory;
    }
    
    public function setInventory($newInventory) {
        $this->inventory = $newInventory;
    }
    
    public function setPrice($newPrice) {
        $this->price = $newPrice;
    }
}

?>
