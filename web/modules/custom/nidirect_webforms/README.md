NIDirect Webforms
---------------

Custom handlers and components for the Webform module.

### Quiz Results Handler

Displays user scores and feedback.

1. Create questions (radios/checkboxes) using the machine name *question_XX* 
(replacing XX with a number).
2. Click on the Webform settings and *Emails/Handlers*.
3. Add a new handler and click on *Quiz Results* from the popup.
4. Complete the handler settings outlined below.
    
#### Quiz Results settings    
    
**General Tab**
* **Results Text** 
    * Introduction HTML text.
    * Pass HTML text.
    * Fail HTML text.
    * Feedback introduction HTML text.
* **Answers** 
    * Minimum score to pass the quiz.
    * Question sections (for each *question_XX* machine name).
        * Correct answer.
        * Correct answer HTML text feedback.  
        * Incorrect answer HTML text feedback.  

**Advanced Tab**
* **Advanced Settings** 
    * Display score on results page.
    * Display feedback on results page.
    * Delete quiz submissions when completed.

#### Quiz Results templates
The Quiz Results handler provides 2 twig templates:
* nidirect_webforms_quiz_results - Template for Quiz Results section.
* nidirect_webforms_quiz_answer_feedback - Template for answer feedback.
